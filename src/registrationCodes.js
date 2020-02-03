const { ApplicationError } = require('./errorHandling');

/**
 * Makes the special code checking closure.
 * "Special Codes" are tracked in firebase instead of salesforce.
 * @param firebase
 * @returns {(code: string): object|undefined}
 */
function makeSpecialCodeChecker(firebase) {
  return async function checkSpecialCode(code) {
    const registrationCodes = await firebase.getReference('registration-codes');
    switch (code) {
      case registractionCodes['individual-volunteer-distributors']:
        return {
          success: true,
          volunteerType: 'volunteerDistributor',
          isIndividualDistributor: true
        };
      case registrationCodes['tat-ambassadors']:
        return {
          success: true,
          volunteerType: 'ambassadorVolunteer'
        };
    }
  };
}

/**
 * Makes the regular code checking closure.
 * Regular codes are tracked in salesforce.
 * @param salesforce
 * @returns {(code: string): object}
 */
function makeRegularCodeChecker(salesforce) {
  return async function checkRegularCode(code) {
    // TODO: get from salesforce
    // Unsure what the interface would look like for this query.
    const records = [];

    if (records.length === 0) {
      throw new ApplicationError({
        message: 'The registration code was incorrect.',
        errorCode: 'INCORRECT_REGISTRATION_CODE'
      });
    }

    // TODO: we should eventually check that there was only 1 record,
    // but the original code did not.
    const accountId = records[0].Id;

    // TODO: get from salesforce
    // Unsure what the interface would look like for this query.
    // The result is an object with FirstName, LastName, and Id.
    // The original code converts it to a nicer object with
    // name, salesforceId
    const coordinators = [];

    return {
      success: true,
      accountId: accountId,
      volunteerType: 'volunteerDistributor',
      isIndividualDistributor: false,
      teamCoordinators: coordinators
    };
  };
}

/**
 * Composes the two checkers once they have been created.
 * Is perhaps evidence that this should just be a class.
 * @param specialCodeChecker
 * @param regularCodeChecker
 */
function makeCombinedCodeChecker(specialCodeChecker, regularCodeChecker) {
  return async function checkRegistrationCode(code) {
    const specialCodeResult = await specialCodeChecker(code);
    if (specialCodeResult !== undefined) return specialCodeResult;
    return await regularCodeChecker(code);
  };
}

module.exports = {
  makeSpecialCodeChecker,
  makeRegularCodeChecker,
  makeCombinedCodeChecker
};
