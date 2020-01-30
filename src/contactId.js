/**
 * Maps Firebase User IDs to Salesforce Contact IDs
 *
 * Is cached locally in either sqlite or a json file. If the userID isn't in the cache,
 * we'll have to reach out to Salesforce to check.
 */
class UserIDCache {
  constructor(sqlite, fallback) {
    this.sqlite = sqlite;
    this.fallback = fallback;
  }
}
