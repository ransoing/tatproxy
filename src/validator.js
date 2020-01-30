module.exports = function(joiSchema) {
  return function(req, res, next) {
    const { error, value } = joiSchema.validate(req.body);
    if (error) {
      res.status(400).send(`validation error: ${error}`);
    } else {
      next();
    }
  };
};
