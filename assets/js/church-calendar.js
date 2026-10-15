(() => {
    window.cmmpInitChurchCalendar = function cmmpInitChurchCalendar(options) {
        const element = document.getElementById(options.elementId || 'church-calendar');
        const facility = document.getElementById(options.facilityId || 'calendar-facility');
        const message = document.getElementById(options.messageId || 'calendar-message');
        if (!element || !window.FullCalendar) {
            return null;
        }

        let bookedDates = new Set();
        let calendar = null;

        const localDateStr = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const mergeEventBookedDates = () => {
            const merged = new Set(bookedDates);
            if (!calendar) {
                return merged;
            }
            calendar.getEvents().forEach((event) => {
                if (!event.start) {
                    return;
                }
                const start = new Date(event.start.getTime());
                start.setHours(0, 0, 0, 0);
                const endSource = event.end || event.start;
                const end = new Date(endSource.getTime());
                end.setHours(0, 0, 0, 0);
                for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
                    merged.add(localDateStr(cursor));
                }
            });
            return merged;
        };

        const applyDayStyles = () => {
            const mergedBookedDates = mergeEventBookedDates();
            element.querySelectorAll('.fc-daygrid-day').forEach((cell) => {
                cell.classList.remove('cmmp-calendar-day-available', 'cmmp-calendar-day-booked', 'cmmp-calendar-day-today-mark');
                const dateStr = cell.getAttribute('data-date') || cell.dataset.date;
                if (!dateStr) {
                    return;
                }
                if (cell.classList.contains('fc-day-today')) {
                    cell.classList.add('cmmp-calendar-day-today-mark');
                }
                if (mergedBookedDates.has(dateStr)) {
                    cell.classList.add('cmmp-calendar-day-booked');
                } else {
                    cell.classList.add('cmmp-calendar-day-available');
                }
            });
        };

        calendar = new FullCalendar.Calendar(element, {
            initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
            initialDate: options.initialDate || undefined,
            height: 'auto',
            fixedWeekCount: false,
            dayMaxEvents: 3,
            moreLinkText: (num) => `+${num} more`,
            firstDay: 0,
            displayEventTime: true,
            eventTimeFormat: {hour: 'numeric', minute: '2-digit', meridiem: 'short'},
            headerToolbar: options.headerToolbar || {left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth'},
            events: (info, success, failure) => {
                const query = new URLSearchParams({start: info.startStr, end: info.endStr});
                if (element.dataset.serviceTypeId) {
                    query.set('service_type_id', element.dataset.serviceTypeId);
                }
                if (element.dataset.serviceCode) {
                    query.set('service_code', element.dataset.serviceCode);
                }
                if (facility && facility.value) {
                    query.set('facility_id', facility.value);
                }
                fetch(element.dataset.apiUrl + '?' + query.toString(), {headers: {Accept: 'application/json'}})
                    .then((response) => response.json().then((data) => ({response, data})))
                    .then(({response, data}) => {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Calendar data could not be loaded.');
                        }
                        bookedDates = new Set(data.bookedDates || []);
                        success(data.events || []);
                        if (message) {
                            message.hidden = true;
                        }
                        window.requestAnimationFrame(applyDayStyles);
                    })
                    .catch((error) => {
                        if (message) {
                            message.textContent = error.message;
                            message.hidden = false;
                        }
                        failure(error);
                    });
            },
            eventsSet: () => window.requestAnimationFrame(applyDayStyles),
            datesSet: () => window.requestAnimationFrame(applyDayStyles),
            eventContent: (arg) => {
                const pill = document.createElement('div');
                pill.className = 'cmmp-cal-event-pill';
                const dot = document.createElement('span');
                dot.className = 'cmmp-cal-event-dot';
                dot.setAttribute('aria-hidden', 'true');
                pill.appendChild(dot);
                const label = document.createElement('span');
                label.className = 'cmmp-cal-event-label';
                const timePrefix = arg.timeText ? `${arg.timeText} ` : '';
                label.textContent = `${timePrefix}${arg.event.title}`;
                pill.appendChild(label);
                return {domNodes: [pill]};
            },
            eventClick: (info) => {
                info.jsEvent.preventDefault();
                const details = info.event.extendedProps;
                alert(details.type + ': ' + info.event.title.replace(details.type + ': ', '') + '\n' + info.event.start.toLocaleString());
            },
            dateClick: (info) => {
                if (typeof options.onDateClick !== 'function') {
                    return;
                }
                options.onDateClick(info, calendar, bookedDates);
            },
        });

        if (facility) {
            facility.addEventListener('change', () => calendar.refetchEvents());
        }

        calendar.render();
        window.requestAnimationFrame(applyDayStyles);
        return calendar;
    };
})();
