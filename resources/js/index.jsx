import React from 'react';
import {render} from 'react-dom';
import App from './App';

// Fonts
import 'typeface-roboto';

// Sytylesheets
require('../scss/index.scss');

render(
  <App />,
  document.getElementById('app')
);
