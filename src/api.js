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

  app.get(createdRoutes
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
    '/api/getTeamCoordinators',
    validator(
      Joi.object({
        accountId: Joi.string().required()
      })
    ),
    (req, res) => {
      const { accountId } = req.body;
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
    '/api/createNewUser',
    validator(
      Joi.object({
        firebaseIdToken: Joi.string().required(),
        registrationCode: Joi.string().required(),
        salesforceId: Joi.string(),
        email: Joi.string().email(),
        phone: Joi.string().phone(),
        firstName: Joi.string(),
        lastName: Joi.string(),
        isCoordinator: Joi.boolean(),
        coordinatorId: Joi.string(),
        trainingVideoRequiredForTeam: Joi.boolean()
      })
    ),
    (req, res) => {
      const {
        firebaseIdToken,
        registrationCode,
        salesforceId,
        email,
        phone,
        firstName,
        lastName,
        isCoordinator,
        coordinatorId,
        trainingVideoRequiredForTeam
      } = req.body;
      // TODO
      const responseBody = [
        {
          contactId: ''
        }
      ];
      res.send(responseBody);
    }
  );

  app.post(
    '/api/updateUser',
    validator(
      Joi.object({
        firebaseIdToken: Joi.string().required(),
        isCoordinator: Joi.boolean(),
        hasWatchedTrainingVideo: Joi.boolean(),
        trainingVideoLastWatchedDate: Joi.date().format('YYYY-MM-DD'),
        trainingVideoRequiredForTeam: Joi.boolean()
      })
    ),
    (req, res) => {
      const {
        firebaseIdToken,
        isCoordinator,
        hasWatchedTrainingVideo,
        trainingVideoLastWatchedDate,
        trainingVideoRequiredForTeam
      } = req.body;
      // TODO
      const responseBody = [
        {
          success: true
        }
      ];
      res.send(responseBody);
    }
  );

  app.post(
    '/api/getUserData',
    validator(
      Joi.object({
        firebaseIdToken: Joi.string().required()
      })
    ),
    (req, res) => {
      const { firebaseIdToken } = req.body;
      // TODO
      const responseBody = [
        {
          salesforceId: '',
          firstName: '',
          lastName: '',
          volunteerType: '',
          accountId: '',
          hasWatchedTrainingVideo: false,
          trainingVideoLastWatchedDate: '2020-01-01',
          street: '',
          city: '',
          state: '',
          zip: '',
          country: '',
          isTeamCoordinator: false,
          teamCoordinatorId: '',
          isOnVolunteerTeam: false,
          trainingVideoRequiredForTeam: false,
          notificationPreferences: {
            ['token']: {
              language: '',
              preEventSurveyReminderEnabled: false,
              reportReminderEnabled: false,
              upcomingEventsReminderEnabled: false
            }
          }
        }
      ];
      res.send(responseBody);
    }
  );

  app.post(
    '/api/getCampaigns',
    validator(
      Joi.object({
        firebaseIdToken: Joi.string().required()
      })
    ),
    (req, res) => {
      const { firebaseIdToken } = req.body;
      // TODO
      const responseBody = [
        {
          name: '',
          salesforceId: '',
          daysSinceCreated: '1'
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
    '/api/createPostOutreachReport',
    validator(
      Joi.object({
        email: Joi.string()
          .email()
          .required(),
        outreachLocationId: Joi.string()
          .outreachLocationId()
          .required(),
        totalHours: Joi.number().required(),
        completionDate: Joi.string(Joi.date().format('YYYY-MM-DD')).required(),
        accomplishments: Joi.array()
          .items(Joi.string())
          .required(),
        otherAccomplishments: Joi.string(),
        contactFirstName: Joi.string().required(),
        contactLastName: Joi.string().required(),
        contactTitle: Joi.string().required(),
        contactEmail: Joi.string(),
        contactPhone: Joi.string().required(),
        willFollowUp: Joi.boolean().required(),
        followUpDate: Joi.string(Joi.date().format('YYYY-MM-DD'))
      })
    ),
    (req, res) => {
      const {
        email,
        outreachLocationId,
        totalHours,
        completionDate,
        accomplishments,
        otherAccomplishments,
        contactFirstName,
        contactLastName,
        contactTitle,
        contactEmail,
        contactPhone,
        followUpDate
      } = req.body;
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
