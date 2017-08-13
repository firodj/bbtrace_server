import React, { Component } from 'react';
import Paper from 'material-ui/Paper';
import Grid from 'material-ui/Grid';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import ListBlocks from './ListBlocks';

class FunctionInfo extends Component {
  constructor(props) {
    super(props);
  }

  componentDidMount() {
  }

  render() {
    return (
      <Paper style={{padding: '16px'}}>
        <Grid container gutter={0}>
          <Grid item xs={4}>
            <List disablePadding>
              <ListBlocks blocks={this.props.info.blocks} onClick={this.props.onBlockClick} />
            </List>
          </Grid>
          <Grid item xs={8}>
          </Grid>
        </Grid>
      </Paper>
    );

  }
}


export default FunctionInfo;
