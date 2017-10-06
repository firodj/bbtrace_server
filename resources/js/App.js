/* @flow */

import React, { Component } from 'react';
import {
  BrowserRouter as Router,
  Route,
  Switch,
  Link
} from 'react-router-dom';
import MapPage from './MapPage';

type Props = { /* ... */ }

type State = {
  authenticated: boolean,
  pages: Array<any>
}

class App extends Component<Props, State> {
  state = {
    authenticated: false,
    pages: []
  }

  componentDidMount() {
    const pages = [];

    pages.push(
      <Router key="main">
        <Route exact path='/' render={routeProps => <MapPage {...routeProps} />} />
      </Router>
    );

    this.setState({ pages });
  }

  render() {
    return (
      <div>
        { this.state.pages }
      </div>
    );
  }
};

export default App;
