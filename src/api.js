const validator = require('./validator');
const Joi = require('@hapi/joi');

app.get(
  '/api/checkRegistrationCode',
  validator(Joi.object({
    code: Joi.string().required()
  })),
  (req, res) => {
    const {
      code
    } = req.body;
    // TODO
    const responseBody = {
      success: true,
      volunteerType: 'volunteerDistributor' | 'ambassadorVolunteer',
      // for volunteerDistributor users:
      accountId: '', // representing one or more teams of volunteers
      isIndividualDistributor: true | false,
      teamCoordinators: {
        name: '',
        salesforceId: ''
        // }[] // ???
      }
    };
    res.send(responseBody);
  }
);

app.get(
  '/api/contactSearch',
  validator(
    Joi.object({
      email: Joi.string()
        .email()
        .required(),
      phone: Joi.string().required()
    })
  ),
  (req, res) => {
    const {
      email,
      phone
    } = req.body;
    // TODO
    const responseBody = {
      salesforceId: ''
    };
    res.send(responseBody);
  }
);

app.get(
  '/api/createFeedback',
  validator(
    Joi.object({
      email: Joi.string()
        .email()
        .required(),
      phone: Joi.string().required()
    })
  ),
  (req, res) => {
    const {
      email,
      phone
    } = req.body;
    // TODO
    const responseBody = {
      salesforceId: ''
    };
    res.send(responseBody);
  }
);

app.post(
  '/api/deleteOutreachLocation',
  validator(
    Joi.object({
      firebaseIdToken: Joi.string()
        .firebaseIdToken()
        .required(),
      outreachLocationId: Joi.string()
        .required()
    }), true
  ),
  (req, res) => {

    const {
      firebaseIdToken,
      outreachLocationId
    } = req.body;

    //TODO
    const responseBody = {
      success: true
    };

    res.send(responseBody)
  }
);

app.post(
  '/api/updateNotificationPreferences',
  validator(
    Joi.object({
      firebaseIdToken: Joi.string()
        .firebaseIdToken()
        .required(),
      fcmToken: Joi.string()
        .fcmToken()
        .required(),
      language: Joi.string(),
      preEventSurveyReminderEnabled: Joi.string(),
      reportReminderEnabled: Joi.string(),
      upcomingEventsReminderEnabled: Joi.string()

    }), true
  ),
  (req, res) => {

    const {
      firebaseIdToken,
      fcmToken,
      language,
      preEventSurveyReminderEnabled,
      reportReminderEnabled,
      upcomingEventsReminderEnabled

    } = req.body;

    //TODO
    const responseBody = {
      success: true
    };

    res.send(responseBody)
  }

);
app.post(
  '/api/unregisterFcmToken',
  validator(
    Joi.object({
      firebaseIdToken: Joi.string()
        .firebaseIdToken()
        .required(),
      fcmToken: Joi.string()
        .fcmToken()
        .required()
    }), true
  ),
  (req, res) => {

    const {
      firebaseIdToken,
      fcmToken

    } = req.body;

    //TODO
    const responseBody = {
      success: true
    };

    res.send(responseBody)
  }
);
