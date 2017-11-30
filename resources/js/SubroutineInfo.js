/* @flow */

import React, { Component } from 'react';
import ReactDOM from 'react-dom';

import Paper from 'material-ui/Paper';
import Grid from 'material-ui/Grid';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';
import ListBlocks from './ListBlocks';
import Draggable, { DraggableCore } from 'react-draggable';
import { Graph, Rect, Edge, NormalArrow } from './dagre';

import axios from 'axios';
const sprintf = require('sprintf-js').sprintf;

type Props = {
  subroutine_id: number
}

type State = {
  info: {
    blocks: Array<any>,
    links: Array<any>,
    addr: number,
    end: number,
    name: string,
    id: ?number
  }
}

type Drawing = {
  ref: any,
  panX: number,
  panY: number
}

const emptyInfo = {
  blocks: [],
  links: [],
  addr: 0,
  end: 0,
  name: '',
  id: null
}

const BlockContent = ({ block }) => {
  const lines = [];
  lines.push( <tspan key="label" x="0" y="0" fontWeight="bold">{ sprintf("%d: 0x%x", block.addr, block.addr) }</tspan> );

  if (block.type == 'block') {

    block.codes.forEach((code, idx) => {
      const y = (idx + 1) * 10;
      lines.push( <tspan key={`row-${idx}-a`} x="0" y={y}>{ code.code }</tspan> );
    });
    return (
      <text fontSize={8} fill="black">
        { lines }
      </text>
    );
  } else if (block.type == 'subroutine' || block.type == 'symbol' || block.type == 'other') {
    lines.push( <tspan key="name" x="0" y="10">{ block.name }</tspan> );
  }

  return (
    <text fontSize={8} fill="white">
      { lines }
    </text>
  );
};

class SubroutineInfo extends Component<Props, State> {
  state = {
    info: emptyInfo
  }

  drawing : Drawing = {
    ref: null,
    panX: 0,
    panY: 0
  }

  componentDidMount() {
    this.fetchSubroutine(0);
  }

  componentWillReceiveProps(nextProps: Props) {
    if (this.props.subroutine_id != nextProps.subroutine_id) {
      this.fetchSubroutine(nextProps.subroutine_id);
    }
  }

  render() {
    const styles = {
      simpleBlock: {
        fontSize: 10,
        fontFamily: 'Monaco',
        width: '100%',
      },
      cell: {
        display: 'table-cell'
      },
      smallCell: {
        display: 'table-cell',
        width: 60
      },
    }
    let last_y = 30;

    const info = (
      <div style={styles.simpleBlock}>
        <div>
          <span style={styles.smallCell}>address</span>
          <span style={styles.cell}>{ sprintf("%d", this.state.info.addr) } - { sprintf("%d", this.state.info.end) }</span>
        </div>
        <div>
          <span style={styles.smallCell}>id</span>
          <span style={styles.cell}>{ this.state.info.id }</span>
        </div>
        <div>
          <span style={styles.smallCell}>name</span>
          <span style={styles.cell}>{ this.state.info.name }</span>
        </div>
      </div>
    );

    const rectStyles = {
      unknown: {
        fill: 'black'
      },
      block: {
        fill: 'white',
        stroke: 'blue'
      },
      subroutine: {
        fill: 'green'
      },
      symbol: {
        fill: 'purple'
      },
      other: {
        fill: 'blue'
      },
    };

    return (
      <div style={{padding: 16, width: 600, overflow: 'auto'}} id="infoPaper">
        { info }
        <DraggableCore onDrag={this.handleDrag}>
          <svg width="100%" height="100%">
            <defs>
              <NormalArrow id="markerArrow" />
              <NormalArrow id="markerRedArrow" style={{ fill: 'red', stroke: 'none' }} />
              <NormalArrow id="markerGreenArrow" style={{ fill: 'green', stroke: 'none' }} />
            </defs>
            <Graph ref={(drawing) => this.drawing.ref = drawing} >
              { this.state.info.blocks.map(block => (
                <Rect key={block.id} node={block.id} style={rectStyles[block.type]}>
                  <BlockContent block={block} />
                </Rect>
              ))}
              { this.state.info.links.map(link => (
                <Edge key={link.key} id={link.key} markerEnd={
                    link.condition === true ? "url(#markerGreenArrow)" : (link.condition === false ? "url(#markerRedArrow)" : "url(#markerArrow)")
                  } style={{
                    stroke: (link.condition === true ? 'green' : (link.condition === false ? 'red' : 'black'))
                  }}
                  source={link.source_id} target={link.target_id}
                />
              )) }
            </Graph>
          </svg>
        </DraggableCore>
      </div>
    );
  }

  handleDrag = (e: SyntheticEvent<>, data: DraggableData) => {
    this.panTo(this.drawing.panX + data.deltaX,
      this.drawing.panY + data.deltaY);
  }

  panTo(x: number, y: number) {
    this.drawing.panX = x > 0 ? 0 : x;
    this.drawing.panY = y > 0 ? 0 : y;
    const el = ReactDOM.findDOMNode(this.drawing.ref);
    if (el instanceof Element) {
      el.setAttribute("transform", `translate(${this.drawing.panX}, ${this.drawing.panY})`);
    }
  }

  fetchSubroutine(id: number)
  {
    if (id == 0) {
      return;
    }
    this.panTo(0, 0);
    axios.get(`/api/v1/subroutine/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      })
  }
}


export default SubroutineInfo;
