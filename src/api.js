const validator = require('./validator');
const Joi = require('@hapi/joi');

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
