// Ship the date picker and its date library together so a partially cached or
// failed vendor request cannot initialize the picker without Moment.
const moment = require('moment');
window.moment = moment;
require('daterangepicker');
