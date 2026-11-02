(() => {
    const localDateStr = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const showMessage = (message, text) => {
        if (!message) {
            return;
        }
        message.textContent = text;
        message.hidden = false;
    };

    const hideMessage = (message) => {
        if (!message) {
            return;
        }
        message.hidden = true;
        message.textContent = '';
    };

    const memberDateClick = (info, calendar, bookedDates, element) => {
        const today = localDateStr(new Date());
        if (info.dateStr < today) {
            return;
        }
        if (bookedDates.has(info.dateStr) && !window.confirm('This date already has a confirmed booking for this venue or service. Continue with a different time?')) {
            return;
        }
        const requestUrl = element.dataset.requestUrl;
        if (!requestUrl) {
            return;
        }
        const query = new URLSearchParams({preferred_date: info.dateStr});
        if (element.dataset.serviceTypeId) {
            query.set('service_type_id', element.dataset.serviceTypeId);
        }
        const facility = document.getElementById('calendar-facility');
        if (facility?.value) {
            query.set('preferred_facility_id', facility.value);
        }
        window.location.href = requestUrl + '?' + query.toString();
    };

    window.cmmpInitChurchCalendar = function cmmpInitChurchCalendar(options) {
        const settings = options || {};
        const element = document.getElementById(settings.elementId || 'church-calendar');
        const facility = document.getElementById(settings.facilityId || 'calendar-facility');
        const message = document.getElementById(settings.messageId || 'calendar-message');
        if (!element) {
            return null;
        }
        if (element.dataset.calendarReady === '1') {
            return element._cmmpCalendar || null;
        }
        if (!window.FullCalendar) {
            showMessage(message, 'The calendar could not be loaded. Refresh the page, or check that the FullCalendar script is allowed.');
            return null;
        }

        let bookedDates = new Set();
        let calendar = null;

        const applyDayStyles = () => {
            element.querySelectorAll('.fc-daygrid-day').forEach((cell) => {
                cell.classList.remove('cmmp-calendar-day-available', 'cmmp-calendar-day-booked', 'cmmp-calendar-day-today-mark');
                const dateStr = cell.getAttribute('data-date') || cell.dataset.date;
                if (!dateStr) {
                    return;
                }
                if (cell.classList.contains('fc-day-today')) {
                    cell.classList.add('cmmp-calendar-day-today-mark');
                }
                if (bookedDates.has(dateStr)) {
                    cell.classList.add('cmmp-calendar-day-booked');
                } else {
                    cell.classList.add('cmmp-calendar-day-available');
                }
            });
        };

        const onDateClick = typeof settings.onDateClick === 'function'
            ? settings.onDateClick
            : (element.dataset.requestUrl
                ? (info, currentCalendar, dates) => memberDateClick(info, currentCalendar, dates, element)
                : null);

        calendar = new FullCalendar.Calendar(element, {
            initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
            initialDate: settings.initialDate || element.dataset.initialDate || undefined,
            height: 'auto',
            fixedWeekCount: false,
            dayMaxEvents: 3,
            moreLinkText: (num) => `+${num} more`,
            firstDay: 0,
            displayEventTime: true,
            eventTimeFormat: {hour: 'numeric', minute: '2-digit', meridiem: 'short'},
            headerToolbar: settings.headerToolbar || {left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth'},
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
                } else if (element.dataset.facilityId) {
                    query.set('facility_id', element.dataset.facilityId);
                }
                fetch(element.dataset.apiUrl + '?' + query.toString(), {headers: {Accept: 'application/json'}})
                    .then((response) => response.json().then((data) => ({response, data})))
                    .then(({response, data}) => {
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Calendar data could not be loaded.');
                        }
                        bookedDates = new Set(data.bookedDates || []);
                        success(data.events || []);
                        hideMessage(message);
                        window.requestAnimationFrame(applyDayStyles);
                    })
                    .catch((error) => {
                        showMessage(message, error.message || 'Calendar data could not be loaded.');
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
                const details = info.event.extendedProps || {};
                const type = details.type || 'Booking';
                alert(type + ': ' + info.event.title.replace(type + ': ', '') + '\n' + info.event.start.toLocaleString());
            },
            dateClick: (info) => {
                if (typeof onDateClick !== 'function') {
                    return;
                }
                onDateClick(info, calendar, bookedDates);
            },
        });

        if (facility) {
            facility.addEventListener('change', () => calendar.refetchEvents());
        }

        calendar.render();
        element.dataset.calendarReady = '1';
        element._cmmpCalendar = calendar;
        window.requestAnimationFrame(applyDayStyles);
        return calendar;
    };

    const start = () => {
        if (document.getElementById('church-calendar')) {
            window.cmmpInitChurchCalendar({});
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
