import React from 'react';
import Button from 'material-ui/Button';
import Grid from 'material-ui/Grid';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import StarBorder from 'material-ui-icons/StarBorder';
import Paper from 'material-ui/Paper';
import Typography from 'material-ui/Typography';


const sprintf = require('sprintf-js').sprintf;

import axios from 'axios';

class Root extends React.Component {
  constructor(props) {
    super(props);
    this.handleBlockClick = this.handleBlockClick.bind(this);
    this.state = {
      blocks: [],
      info: null,
    };
  }

  componentDidMount() {
    axios.get('/api/v1/blocks')
      .then(res => {
        const blocks = res.data;
        this.setState({ blocks });
      });
  }

  handleBlockClick(id) {
    axios.get(`/api/v1/block/${id}`)
      .then(res => {
        const info = JSON.stringify(res.data);
        this.setState({ info });
      });
  }

  render() {
    const paperStyle = {
      padding: '16px'
    };

    const listStyle = {
      overflowY: 'auto',
      height: '100vh',
      padding: 0,
    }

    return (
      <div>
        <Grid container gutter={0}>
          <Grid item xs={2}>
            <List style={listStyle} dense={true} disablePadding={true}>
              <ListBlocks blocks={this.state.blocks} onClick={this.handleBlockClick} />
              <ListMore />
            </List>
          </Grid>
          <Grid item xs={10}>
            <Paper style={paperStyle}>
              <Typography type="body1">
                { this.state.info }
              </Typography>
            </Paper>
          </Grid>
        </Grid>
      </div>
    );
  }
}

const ListBlocks = ({ blocks, onClick }) => <div>{ blocks.map( (block) =>
    <ListItem button key={block.id} onClick={() => onClick(block.id)}>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary={sprintf("%X", block.id)} />
    </ListItem>
    ) }
  </div>
;

const ListMore = () =>
    <ListItem button key={0} onClick={() => alert('more')}>
      <ListItemText primary="More" />
    </ListItem>
;

export default Root;
