/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single CSS file (app.css in this case)
import './styles/app.css';
import './styles/global.scss';

// start the Stimulus application
import './bootstrap';

import '@popperjs/core';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;
window.Popper = require('@popperjs/core');

require('bootstrap');

import './index';
import './sf-versions';
import './quiz';
import './results';
