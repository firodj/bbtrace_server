/* @flow */

import React, { Component } from 'react';
import {
  BrowserRouter as Router,
  Route,
  Switch,
  Link
} from 'react-router-dom';
import MapPage from './MapPage';
import SubroutinePage from './SubroutinePage';

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
        <div>
          <Route exact path='/' render={routeProps => <MapPage {...routeProps} />} />
          <Route path='/function' render={routeProps => <SubroutinePage {...routeProps} />} />
        </div>
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
