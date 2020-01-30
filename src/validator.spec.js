describe('validation middleware', () => {
  let sut = require('./validator');

  it('should return a validator function', () => {
    expect(typeof sut).toBe('function');
    expect(typeof sut()).toBe('function');
  });
  it('should validate based on a Joi schema', () => {
    const validatorMiddleware = sut(
      Joi.object({
        username: Joi.string()
          .alphanum()
          .min(3)
          .max(30)
          .required()
      })
    );
    const req = {
      body: {
        username: 'my username'
      }
    };
    const resSpy = jest.fn();
    const res = { status: jest.fn(), send: resSpy };
    const next = jest.fn();
    expect(validatorMiddleware(req, res, next));
    expect();
  });
});
