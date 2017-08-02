import React from 'react';
import {render} from 'react-dom';
import Root from './Root';

import injectTapEventPlugin from 'react-tap-event-plugin';

// Needed for onTouchTap
// http://stackoverflow.com/a/34015469/988941
injectTapEventPlugin();

// Fonts
import 'typeface-roboto';

// Sytylesheets
require('../scss/index.scss');

// Components
const App = () => (
    <Root />
);

render(
  <App />,
  document.getElementById('app')
);
