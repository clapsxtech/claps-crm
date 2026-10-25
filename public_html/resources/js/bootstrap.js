// Keep legacy jQuery/Bootstrap APIs while declaring every runtime dependency directly.
window._ = require('lodash');
window.Popper = require('popper.js').default;
window.$ = window.jQuery = require('jquery');
require('bootstrap');
require('../vendor/admin-lte/adminlte.js');
require('select2')(window, window.jQuery);
window.toastr = require('toastr');
window.toastr.options.escapeHtml = true;

window.axios = require('axios').default;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
