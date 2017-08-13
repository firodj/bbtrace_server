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
import FunctionInfo from './FunctionInfo';
import { orangeAvatar } from './colors';
import Avatar from 'material-ui/Avatar';
import ListBlocks from './ListBlocks';
import ListFunctions from './ListFunctions';

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
    this.handleMenuClick = this.handleMenuClick.bind(this);
    this.handleFunctionClick = this.handleFunctionClick.bind(this);
    this.handleFunctionStepperClick = this.handleFunctionStepperClick.bind(this);
    this.state = {
      blocks: [],
      functions: {
        items: [],
        steps: 0,
        activeStep: 0,
      },
      info: {
        type: null,
      },
      offset: 0,
      hasPrev: false,
      hasMore: true,
      open: {
        right: false,
      },
      activePage: 'blocks',
    };
  }

  componentDidMount() {
    this.appendBlocks(this.state.offset);
    this.appendFunctions(this.state.functions.activeStep);
  }

  handleBlockClick(id) {
    axios.get(`/api/v1/block/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleFunctionClick(id) {
    axios.get(`/api/v1/function/${id}`)
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

  handleMenuClick(item)
  {
    this.setState({ activePage: item, info: { type: null } });
  }

  handleFunctionStepperClick(direction)
  {
    this.appendFunctions(this.state.functions.activeStep + direction);
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

  appendFunctions(activeStep) {
    axios.get('/api/v1/functions', {
      params: { activeStep }
    })
      .then(res => {
        const data = res.data;
        this.setState({
          functions: data, // this.state.blocks.concat(data.blocks) 
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
          <Grid item xs={3}>
            { this.state.activePage == 'blocks' && <List style={listStyle} dense={true} disablePadding={true}>
              { this.state.hasPrev && <ListButton primary="Prev" onClick={this.handlePrevClick} /> }
              <ListBlocks blocks={this.state.blocks} onClick={this.handleBlockClick} />
              { this.state.hasMore && <ListButton primary="More" onClick={this.handleMoreClick} /> }
            </List> }
            { this.state.activePage == 'functions' &&
              <ListFunctions functions={this.state.functions.items}
                onClick={this.handleFunctionClick}
                steps={this.state.functions.steps}
                activeStep={this.state.functions.activeStep}
                onStepperClick={this.handleFunctionStepperClick}
                />
            }
          </Grid>
          <Grid item xs={9}>
            { this.state.info.type == 'block' && <BlockInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
            { this.state.info.type == 'function' && <FunctionInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
          </Grid>
        </Grid>
        <Drawer
          anchor="right"
          open={this.state.open.right}
          onRequestClose={this.handleRightClose}
          onClick={this.handleRightClose}>
          <List style={menuStyle} disablePadding>
            <ListMenus onMenuClick={ this.handleMenuClick }/>
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

const smallAvatar = {
  width: 20,
  height: 20,
};

const ListButton = ({ primary, onClick }) =>
    <ListItem button onClick={onClick}>
      <ListItemText primary={primary} />
    </ListItem>
;

const ListMenus = ({ onMenuClick }) => <div>
    <ListItem button onClick={ () => onMenuClick('blocks') }>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Blocks" />
    </ListItem>
    <ListItem button onClick={ () => onMenuClick('functions') }>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Functions" />
    </ListItem>
  </div>
;

export default Root;
