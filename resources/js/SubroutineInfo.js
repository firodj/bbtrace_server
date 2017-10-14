/* @flow */

import React, { Component } from 'react';
import Paper from 'material-ui/Paper';
import Grid from 'material-ui/Grid';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import ListBlocks from './ListBlocks';
import Draggable from 'react-draggable';

import axios from 'axios';
const sprintf = require('sprintf-js').sprintf;

type Props = {
  subroutine_id: number
}

type State = {
  info: {
    blocks: Array<any>
  }
}

class SubroutineInfo extends Component<Props, State> {
  state = {
    info: {
      blocks: [],
      id: 0,
      name: '',
    }
  }

  componentDidMount() {
    this.fetchSubroutine(0);
  }

  componentWillReceiveProps(nextProps: Props) {
    const { subroutine_id } = nextProps;
    this.fetchSubroutine(subroutine_id);
  }

  render() {
    const blocks = {};
    let last_y = 30;

    return (
      <div style={{padding: 16, width: 600, overflow: 'auto'}} id="infoPaper">
        <div className="simple-block" style={{ top: 0, left: 0 }}>
          <div className="instruction-row">
            <span className="instruction-cell instruction-cell_small">address</span>
            <span className="instruction-cell">{ sprintf("0x%x", this.state.info.id) }</span>
          </div>
          <div className="instruction-row">
            <span className="instruction-cell instruction-cell_small">name</span>
            <span className="instruction-cell">{ this.state.info.name }</span>
          </div>
        </div>
        { this.state.info.blocks.map(block => {

          if (blocks[block.id] === undefined) {
            blocks[block.id] = {
              x: 0,
              y: last_y,
              h: null,
            };
          }

          if (blocks[block.id].h === null) {
              blocks[block.id].h = 12 * block.insn.length;
              last_y += blocks[block.id].h + 30;
          }

          return (
          <Draggable key={ block.id } handle=".handle">
          <div className="basic-block" style={{ top: blocks[block.id].y, left: blocks[block.id].x }}>
              <div className="instruction-row instruction-row_title handle">
                <span className="instruction-cell instruction-cell_small">{ sprintf("0x%x:", block.id) }</span>
              </div>
            { block.insn.map(inst => {
              return (
              <div key={inst.address} className="instruction-row">
                <span className="instruction-cell instruction-cell_small">{ inst.mnemonic }</span>
                <span className="instruction-cell">{ inst.op_str } { inst.notes || '' }</span>
              </div>
              ); }
            ) }
              <div className="instruction-row">
                <span className="instruction-cell instruction-cell_small">{ sprintf("> 0x%x", block.end) }</span>
              </div>
          </div>
          </Draggable>
          );

          }
        ) }
      </div>
    );
  }

  fetchSubroutine(id: number)
  {
    if (id == 0) {
      return;
    }
    axios.get(`/api/v1/subroutine/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }
}


export default SubroutineInfo;
