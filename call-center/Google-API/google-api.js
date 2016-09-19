$(function() {

});

var googleApiCred = {
    "clientId": "1017269912417-ifu4npqvjaf1m2l6fsoh2sun5grn9mkc.apps.googleusercontent.com",
    "apiKey": "",
    "scopes": "https://www.googleapis.com/auth/calendar"
};


function googleApiAuthCheck(callback) {
    gapi.auth.authorize(
    {
        'client_id': googleApiCred['clientId'],
        'scope': googleApiCred['scopes'],
        'immediate': true
    }).then(function(authRes) {
        callback(true);
    }, function(reason) {
        callback(false);
    });
}

function googleApiAuth(event) {
    gapi.auth.authorize(
        {
            'client_id': googleApiCred['clientId'],
            'scope': googleApiCred['scopes'],
            'immediate': false
        }).then(function(authRes) {
            loadCalendarApi();
            if (event) {
                createGCalEvent(event);
            }
        }, function(reason) {
            showNotification($contact_form, 'Не удалось авторизоваться в Google Calendar', 'top', 'danger', 'bar', 5000);
        });
}

function processGApiAuth() {
    googleApiAuthCheck(function (status) {
            if (!status) {
                googleApiAuth()
            }
        });
}

function loadCalendarApi() {
    return gapi.client.load('calendar', 'v3');
}

function createGCalEvent(event) {
    googleApiAuthCheck(function (status) {
        if (status) {
            loadCalendarApi().then(function() {
                var request = gapi.client.calendar.events.insert({
                    'calendarId': 'primary',
                    'resource': event
                });

                request.then(function(event) {
                    var eventLink = '<a target="_blank" href="' + event.result.htmlLink + '">' + event.result.htmlLink + '</a>';
                    console.log('Ивент добавлен');
                    console.log(eventLink);
                    showNotification($contact_form, 'Событие добавлено в календарь. Ссылка: ' + eventLink, 'top', 'success', 'bar', 10000);
                }, function(reason) {
                    console.log('Error: ' + reason.result.error.message);
                    showNotification($contact_form, 'Событие не было добавлено в календарь.', 'top', 'danger', 'bar', 5000);
                });
            });
        } else {
            googleApiAuth(event);
        }

        });

}

function createGEventData(type, date) {
    var formatDate = convertDate(date);
    var event = {
        summary: '',
        description: '',
        start: {
            dateTime: formatDate
        },
        end: {
            dateTime: formatDate
        }
    };
    switch (type) {
        case "visit_on_date":
            event.summary = "Визит клиента";
            event.description = "Визит клиента";
            break;
        case "object_show":
            event.summary = "Показ обьекта";
            event.description = "Показ обьекта";
            break;
        case "action_call":
            event.summary = "Звонок клиенту";
            event.description = "Звонок клиенту";
            break;
        case "action_email":
            event.summary = "Емейл клиенту";
            event.description = "Емейл клиенту";
            break;
        default :
            event.summary = "Какое-то действие";
            event.description = "Какое-то действие";
    }

    return event;
}

function convertDate(dateTime) {
    var dateTime = dateTime.split(" ");

    var date = dateTime[0].split(".");
    var time = dateTime[1].split(":");

    var dateObj = new Date(date[2], date[1] - 1, date[0], time[0], time[1], 0);
    var day = (dateObj.getDate() < 10) ? '0' + dateObj.getDate() : dateObj.getDate();
    var month = ((dateObj.getMonth() + 1) < 10) ? '0' + (dateObj.getMonth() + 1) : (dateObj.getMonth() + 1);
    var formatDate = dateObj.getFullYear() + "-" + month + "-" + day + "T" +  dateObj.getHours() + ":" + dateObj.getMinutes() + ":" + dateObj.getSeconds() + '0' + "+02:00";

    return formatDate;
}

