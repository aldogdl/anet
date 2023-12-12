importScripts("https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js");
importScripts("https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js");

firebase.initializeApp({
  apiKey: 'AIzaSyA0TyOoWLbwYuViCDh00OTYRx8TBCzntKc',
  authDomain: 'cotizo-ccf4d.firebaseapp.com',
  projectId: 'cotizo-ccf4d',
  storageBucket: 'cotizo-ccf4d.appspot.com',
  messagingSenderId: '41420743420',
  appId: '1:41420743420:web:a48ab2f299af6212ba2d79',
});

const messaging = firebase.messaging();
console.log("Messanging OK!");

messaging.onBackgroundMessage((message) => {});
