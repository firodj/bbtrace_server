import React from 'react';
import {render} from 'react-dom';

// Components
class App extends React.Component {
  render () {
    return <p>Ehm... Hello React!</p>;
  }
}

// Sytylesheets
require('../scss/index.scss');

render(<App/>, document.getElementById('app'));
