import React from 'react';
import {render} from 'react-dom';
import App from './App';

import injectTapEventPlugin from 'react-tap-event-plugin';

// Needed for onTouchTap
// http://stackoverflow.com/a/34015469/988941
injectTapEventPlugin();

// Fonts
import 'typeface-roboto';

// Sytylesheets
require('../scss/index.scss');

render(
  <App />,
  document.getElementById('app')
);
