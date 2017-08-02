import React from 'react';
import Button from 'material-ui/Button';
import Grid from 'material-ui/Grid';
import AppBar from 'material-ui/AppBar';
import Toolbar from 'material-ui/Toolbar';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import Drawer from 'material-ui/Drawer';
import StarBorder from 'material-ui-icons/StarBorder';
import Typography from 'material-ui/Typography';
import IconButton from 'material-ui/IconButton';
import MenuIcon from 'material-ui-icons/Menu';
import BlockInfo from './BlockInfo';

const sprintf = require('sprintf-js').sprintf;

import axios from 'axios';

class Root extends React.Component {
  constructor(props) {
    super(props);
    this.handleBlockClick = this.handleBlockClick.bind(this);
    this.handleMoreClick = this.handleMoreClick.bind(this);
    this.handlePrevClick = this.handlePrevClick.bind(this);
    this.handleRightOpen = this.handleRightOpen.bind(this);
    this.handleRightClose = this.handleRightClose.bind(this);
    this.state = {
      blocks: [],
      info: {
        type: null,
      },
      offset: 0,
      hasPrev: false,
      hasMore: true,
      open: {
        right: false,
      },
    };
  }

  componentDidMount() {
    this.appendBlocks(this.state.offset);
  }

  handleBlockClick(id) {
    axios.get(`/api/v1/block/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleMoreClick() {
    if (this.state.hasMore) {
      this.appendBlocks(this.state.offset + this.props.limit);
    }
  }

  handlePrevClick() {
    if (this.state.hasPrev) {
      this.appendBlocks(this.state.offset - this.props.limit);
    }
  }

  handleRightClose() {
    this.setState({
      open: { right: false }
    });
  }
  handleRightOpen() {
    this.setState({
      open: { right: true }
    });
  }

  appendBlocks(offset, limit) {
    axios.get('/api/v1/blocks', {
      params: { offset, limit }
    })
      .then(res => {
        const data = res.data;
        this.setState({
          offset: data.offset,
          hasMore: data.hasMore,
          hasPrev: data.hasPrev,
          blocks: data.blocks, // this.state.blocks.concat(data.blocks) 
        });
      });
  }

  render() {
    const listStyle = {
      overflowY: 'auto',
      height: '100vh',
      padding: 0,
    };

    const menuStyle = {
      width: 250,
      flex: 'initial',
    };

    const flexStyle = {
      flex: 1,
    };

    return (
      <div>
        <AppBar position="static">
          <Toolbar>
            <Typography type="title" color="inherit" style={flexStyle}>
              { this.props.title }
            </Typography>
            <IconButton color="contrast" aria-label="Menu" onClick={this.handleRightOpen}>
              <MenuIcon />
            </IconButton>
          </Toolbar>
        </AppBar>
        <Grid container gutter={0}>
          <Grid item xs={2}>
            <List style={listStyle} dense={true} disablePadding={true}>
              { this.state.hasPrev && <ListButton primary="Prev" onClick={this.handlePrevClick} /> }
              <ListBlocks blocks={this.state.blocks} onClick={this.handleBlockClick} />
              { this.state.hasMore && <ListButton primary="More" onClick={this.handleMoreClick} /> }
            </List>
          </Grid>
          <Grid item xs={10}>
            { this.state.info.type === 'block' && <BlockInfo info={this.state.info} /> }
          </Grid>
        </Grid>
        <Drawer
          anchor="right"
          open={this.state.open.right}
          onRequestClose={this.handleRightClose}
          onClick={this.handleRightClose}>
          <List style={menuStyle} disablePadding>
            <ListMenus />
          </List>
        </Drawer>
      </div>
    );
  }
}

Root.defaultProps = {
  limit: 100,
  title: window.env.name,
};

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

const ListButton = ({ primary, onClick }) =>
    <ListItem button onClick={onClick}>
      <ListItemText primary={primary} />
    </ListItem>
;

const ListMenus = () => <div>
    <ListItem button>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Blocks" />
    </ListItem>
  </div>
;

export default Root;
