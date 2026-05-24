(function (Drupal, drupalSettings) {
  Drupal.behaviors.bookingCalendar = {
    attach: function (context, settings) {
      const el = context.querySelector('#booking-calendar');
      if (!el || el.dataset.calendarInitialized) {
        return;
      }
      el.dataset.calendarInitialized = true;

      const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        height: 'auto',
        events: settings.bookingCalendar.feedUrl,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        eventTimeFormat: {
          hour: '2-digit',
          minute: '2-digit',
          hour12: false,
        },
        eventClick: function (info) {
          info.jsEvent.preventDefault();
          if (info.event.url) {
            window.location.href = info.event.url;
          }
        },
      });

      calendar.render();
    },
  };
})(Drupal, drupalSettings);
