module.exports = function(joiSchema, validateBody) {
  return function(req, res, next) {
    const { error, value } = joiSchema.validate(
      validateBody ? req.body : req.query
    );
    if (error) {
      res.status(400).send(`validation error: ${error}`);
    } else {
      next();
    }
  };
};
