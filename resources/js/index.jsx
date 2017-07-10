import React from 'react';
import {render} from 'react-dom';
import MuiThemeProvider from 'material-ui/styles/MuiThemeProvider';
import MyAwesomeReactComponent from './MyAwesomeReactComponent';

import injectTapEventPlugin from 'react-tap-event-plugin';

// Needed for onTouchTap 
// http://stackoverflow.com/a/34015469/988941
injectTapEventPlugin();

// Components
const App = () => (
  <MuiThemeProvider>
    <MyAwesomeReactComponent />
  </MuiThemeProvider>
);

// Sytylesheets
require('../../node_modules/roboto-fontface/css/roboto/sass/roboto-fontface-regular.scss');
require('../scss/index.scss');

render(
  <App />,
  document.getElementById('app')
);
