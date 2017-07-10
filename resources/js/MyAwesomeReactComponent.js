import React from 'react';
import RaisedButton from 'material-ui/RaisedButton';
import { Grid, Row, Col } from 'react-flexbox-grid';

const MyAwesomeReactComponent = () => (
  <Grid fluid>
    <Row>
      <Col xs={6} md={3}>
        <RaisedButton label="Default" />
      </Col>
    </Row>
  </Grid>
);

export default MyAwesomeReactComponent;
