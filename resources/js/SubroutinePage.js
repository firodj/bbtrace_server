/* @flow */

import React, { Component } from 'react';
import Grid from 'material-ui/Grid';
import AppBar from 'material-ui/AppBar';
import Toolbar from 'material-ui/Toolbar';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import Drawer from 'material-ui/Drawer';
import StarBorder from 'material-ui-icons/StarBorder';
import Typography from 'material-ui/Typography';
import IconButton from 'material-ui/IconButton';
import MenuIcon from 'material-ui-icons/Menu';
import Paper from 'material-ui/Paper';
import Avatar from 'material-ui/Avatar';

import { orangeAvatar } from './colors';
import BlockInfo from './BlockInfo';
import SubroutineInfo from './SubroutineInfo';
import ListBlocks from './ListBlocks';
import ListSubroutines from './ListSubroutines';

import axios from 'axios';

type Props = {
  limit: number,
  title?: string
}

type State = {
  activePage: string,
  open: {
    right: boolean,
  },
  info: {
    id: number,
    end: number,
    name: string,
    blocks: Array<any>,
  },
  hasPrev: boolean,
  hasMore: boolean,
  offset: number,
  subroutines: {
    items: Array<any>,
    steps: number,
    activeStep: number,
  },
}

class SubroutinePage extends Component<Props, State> {
  state = {
    activePage: 'subroutines',
    open: {
      right: false
    },
    info: {
      id: 0,
      end: 0,
      name: '',
      blocks: []
    },
    hasPrev: false,
    hasMore: true,
    offset: 0,
    blocks: [],
    subroutines: {
      items: [],
      steps: 0,
      activeStep: 0,
    },
  }

  static defaultProps = {
    limit: 100,
    title: window.env.name,
  };

  componentDidMount() {
    this.appendSubroutines(0);
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
        <Grid container spacing={0}>
          <Grid item xs={3}>
            { this.state.activePage == 'blocks' && <List style={listStyle} dense={true} disablePadding={true}>
              { this.state.hasPrev && <ListButton primary="Prev" onClick={this.handlePrevClick} /> }
              <ListBlocks blocks={this.state.blocks} onClick={this.handleBlockClick} />
              { this.state.hasMore && <ListButton primary="More" onClick={this.handleMoreClick} /> }
            </List> }
            { this.state.activePage == 'subroutines' &&
              <ListSubroutines subroutines={this.state.subroutines.items}
                onClick={this.handleSubroutineClick}
                steps={this.state.subroutines.steps}
                activeStep={this.state.subroutines.activeStep}
                onStepperClick={this.handleSubroutineStepperClick}
                />
            }
          </Grid>
          <Grid item xs={9}>
            { this.state.info.type == 'block' && <BlockInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
            { this.state.info.id != 0 && <SubroutineInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
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

  appendBlocks(offset: number, limit: number) {
    axios.get('/api/v1/blocks', {
      params: { offset, limit }
    })
      .then(res => {
        const data = res.data;
        this.setState({
          offset: data.from,
          hasMore: data.current_page < data.last_page,
          hasPrev: data.current_page > 1,
          blocks: data.data, // this.state.blocks.concat(data.blocks) 
        });
      });
  }

  appendSubroutines(activeStep: number) {
    axios.get('/api/v1/subroutines', {
      params: { page: activeStep + 1 }
    })
    .then(res => {
      const data = res.data;
      this.setState({
        subroutines: {
          items: data.data,
          steps: data.last_page,
          activeStep: data.current_page-1
        }
      });
    });
  }


  handleMenuClick = (item: string) => {
    this.setState({ activePage: item, info: { type: null } });
  }

  handleRightClose = () => {
    this.setState({
      open: { right: false }
    });
  }

  handleRightOpen = () => {
    this.setState({
      open: { right: true }
    });
  }

  handleMoreClick = () => {
    if (this.state.hasMore) {
      this.appendBlocks(this.state.offset+ this.props.limit, this.props.limit);
    }
  }

  handlePrevClick = ()  => {
    if (this.state.hasPrev) {
      this.appendBlocks(this.state.offset - this.props.limit, this.props.limit);
    }
  }

  handleBlockClick = (id: number) => {
    axios.get(`/api/v1/block/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleSubroutineClick = (id: number) => {
    axios.get(`/api/v1/subroutine/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleSubroutineStepperClick = (direction: number) => {
    console.log(direction);
    this.appendSubroutines(this.state.subroutines.activeStep + direction);
  }
}

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
    <ListItem button onClick={ () => onMenuClick('subroutines') }>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Subroutines" />
    </ListItem>
  </div>
;

export default SubroutinePage;
