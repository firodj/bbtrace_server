import React, { Component } from 'react';
import Paper from 'material-ui/Paper';
import TextField from 'material-ui/TextField';
import Typography from 'material-ui/Typography';

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
        <TextField
          id="block_entry"
          label="Entry"
          value={sprintf("%X", this.props.info.block_entry)}
          style={textFieldStyle}
          margin="normal"
        />
        <TextField
          id="block_end"
          label="End"
          value={sprintf("%X", this.props.info.block_end)}
          style={textFieldStyle}
          margin="normal"
        />
        <TextField
          id="block_end"
          label="End"
          value={sprintf("%X", this.props.info.block_end)}
          style={textFieldStyle}
          margin="normal"
        />
        { this.props.info.function && <TextField
          id="function_entry"
          label="Function"
          value={sprintf("%X", this.props.info.function.function_entry)}
          style={textFieldStyle}
          margin="normal"
        /> }
        { this.props.info.function && <TextField
          id="function_name"
          label="Name"
          value={sprintf("%s", this.props.info.function.function_name)}
          style={textFieldStyle}
          margin="normal"
        /> }
        { this.props.info.jump && <TextField
          id="jump_mnemonic"
          label="Jump"
          value={sprintf("%s", this.props.info.jump.mnemonic)}
          style={textFieldStyle}
          margin="normal"
        /> }
        { this.props.info.jump.target && <TextField
          id="jump_target"
          label="Target"
          value={sprintf("%X", this.props.info.jump.target)}
          style={textFieldStyle}
          margin="normal"
        /> }
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
