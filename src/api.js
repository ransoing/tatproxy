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
};
