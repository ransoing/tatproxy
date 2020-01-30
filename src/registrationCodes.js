// Makes the special-code checking closure.
const registrationCodesFirebaseRef = 'registration-codes';

function makeSpecialCodeChecker(firebase) {
  return async function checkSpecialCode(code) {
    const registrationCodes = await firebase.getReference(
      registrationCodesFirebaseRef
    );
    switch (code) {
      case registractionCodes['individual-volunteer-distributors']:
        // todo
        break;
      default:
        break;
    }
  };
}
