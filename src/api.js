const validator = require('./validator');
const Joi = require('@hapi/joi');

module.exports = function(app) {
  app.get(
    '/api/checkRegistrationCode',
    validator(Joi.object({ code: Joi.string().required() })),
    (req, res) => {
      const { code } = req.body;
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
      const { email, phone } = req.body;
      // TODO
      const responseBody = {
        salesforceId: ''
      };
      res.send(responseBody);
    }
  );

  app.get(
    '/api/getTeamCoordinators',
    validator(
      Joi.object({
        accountId: Joi.string().required()
      })
    ),
    (req, res) => {
      const { email, phone } = req.body;
      // TODO
      const responseBody = [
        {
          name: '',
          salesforceId: ''
        }
      ];
      res.send(responseBody);
    }
  );

  app.post(
    '/api/createFeedback',
    validator(
      Joi.object({
        firebaseIdToken: Joi.string().required(),
        campaignId: Joi.string(),
        advice: Joi.string(),
        bestPart: Joi.string(),
        improvements: Joi.string(),
        givesAnonPermission: Joi.string().required(),
        givesNamePermission: Joi.string().required()
      })
    ),
    (req, res) => {
      // TODO
      const responseBody = {
        success: true
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
        outreachLocationId: Joi.string().required()
      }),
      true
    ),
    (req, res) => {
      const { firebaseIdToken, outreachLocationId } = req.body;

      //TODO
      const responseBody = {
        success: true
      };

      res.send(responseBody);
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
      }),
      true
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

      res.send(responseBody);
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
      }),
      true
    ),
    (req, res) => {
      const { firebaseIdToken, fcmToken } = req.body;

      //TODO
      const responseBody = {
        success: true
      };

      res.send(responseBody);
    }
  );
};
