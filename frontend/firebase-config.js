// ─── firebase-config.js ───────────────────────────────────────────────────────
// Single source of truth for Firebase configuration.
// All pages load this file instead of repeating the config inline.
// To update the config, change it here only.

(function() {
  'use strict';

  // Guard: don't initialise twice if multiple pages accidentally load this twice
  if (typeof firebase === 'undefined') {
    console.error('[firebase-config] Firebase SDK not loaded before firebase-config.js');
    return;
  }

  if (firebase.apps && firebase.apps.length > 0) {
    // Already initialised (e.g. hot-reloaded dev environment)
    return;
  }

  const firebaseConfig = {
    apiKey:            "AIzaSyCzk01cTMGKSpvkC9ySFW25wlf_nCn069Q",
    authDomain:        "padelv2-44d08.firebaseapp.com",
    databaseURL:       "https://padelv2-44d08-default-rtdb.europe-west1.firebasedatabase.app",
    projectId:         "padelv2-44d08",
    storageBucket:     "padelv2-44d08.firebasestorage.app",
    messagingSenderId: "924995817181",
    appId:             "1:924995817181:web:091bac5decdfcfbc071534"
  };

  firebase.initializeApp(firebaseConfig);
})();
