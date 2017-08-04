import React, { Component } from 'react';
import Paper from 'material-ui/Paper';
import TextField from 'material-ui/TextField';
import InputLabel from 'material-ui/Input';
import Typography from 'material-ui/Typography';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import Avatar from 'material-ui/Avatar';

import { orangeAvatar, purpleAvatar, blueAvatar } from './colors';

const sprintf = require('sprintf-js').sprintf;

class BlockInfo extends Component {
  constructor(props) {
    super(props);
  }

  componentDidMoint() {
  }

  render() {
    const paperStyle = {
      padding: '16px'
    };
    const textFieldStyle = {
      width: 200,
      marginRight: 10,
    }

    return (<div>
      <Paper style={paperStyle}>
        <List disablePadding>
          <ListItem button>
            <Avatar style={orangeAvatar}>B</Avatar>
            <ListItemText
              primary={sprintf("%X", this.props.info.block_entry)}
              secondary={sprintf("%X", this.props.info.block_end)}
            />
          </ListItem>
          { this.props.info.function && 
          <ListItem button>
            <Avatar style={purpleAvatar}>F</Avatar>
            <ListItemText
              primary={sprintf("%X", this.props.info.function.function_entry)}
              secondary={sprintf("%s", this.props.info.function.function_name)}
            />
          </ListItem>
          }
          { this.props.info.jump &&
          <ListItem button>
            <Avatar style={blueAvatar}>J</Avatar>
            <ListItemText
              primary={this.props.info.jump.target ? sprintf("%X", this.props.info.jump.target) : '???'}
              secondary={sprintf("%s", this.props.info.jump.mnemonic)}
            />
          </ListItem>
          }
        </List>
      </Paper>
      <Paper style={paperStyle}>
       { this.props.info.disasm.map((ins) => (
        <Typography type="body1" key={ ins.address }>
          { ins.mnemonic } { ins.op_str }
        </Typography>
       )) }
      </Paper></div>
    );
  }
};

export default BlockInfo;
