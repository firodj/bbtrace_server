import React from 'react';
import {render} from 'react-dom';

// Components
class App extends React.Component {
  render () {
    return <p>Ehm... Hello React!</p>;
  }
}

// Sytylesheets
require('../../node_modules/roboto-fontface/css/roboto/sass/roboto-fontface-regular.scss');
require('../scss/index.scss');

render(<App/>, document.getElementById('app'));
