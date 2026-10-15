(() => {
    window.cmmpInitChurchCalendar = function cmmpInitChurchCalendar(options) {
        const element = document.getElementById(options.elementId || 'church-calendar');
        const facility = document.getElementById(options.facilityId || 'calendar-facility');
        const message = document.getElementById(options.messageId || 'calendar-message');
        if (!element || !window.FullCalendar) {
            return null;
        }

        let bookedDates = new Set();

        const applyDayStyles = () => {
            element.querySelectorAll('.fc-daygrid-day').forEach((cell) => {
                cell.classList.remove('cmmp-calendar-day-available', 'cmmp-calendar-day-booked');
                const dateStr = cell.dataset.date;
                if (!dateStr) {
                    return;
                }
                if (bookedDates.has(dateStr)) {
                    cell.classList.add('cmmp-calendar-day-booked');
                } else {
                    cell.classList.add('cmmp-calendar-day-available');
                }
            });
        };

        const calendar = new FullCalendar.Calendar(element, {
            initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
            initialDate: options.initialDate || undefined,
            height: 'auto',
            fixedWeekCount: false,
            dayMaxEvents: 1,
            firstDay: 0,
            headerToolbar: options.headerToolbar || {left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth'},
            events: (info, success, failure) => {
                const query = new URLSearchParams({start: info.startStr, end: info.endStr});
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
            datesSet: () => window.requestAnimationFrame(applyDayStyles),
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
        return calendar;
    };
})();
