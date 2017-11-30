import React, { Component } from 'react';
import ReactDOM from 'react-dom';

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
import Paper from 'material-ui/Paper';

import * as d3 from 'd3';
import * as _ from 'lodash';
import { Graph, Rect, Edge, NormalArrow } from './dagre';
import Draggable, { DraggableCore } from 'react-draggable';

import SubroutineInfo from './SubroutineInfo';

const sprintf = require('sprintf-js').sprintf;

import axios from 'axios';

type Props = {
  history: any,
  match: any
}

class MapPage extends Component<Props> {
  state = {
    open_right: false,
    nodes: [],
    links: [],
    subroutine_id: 0
  };

  drawing = {
    ref: null,
    panX: 0,
    panY: 0
  }

  updateGraph(data)
  {
    const { nodes, links, subroutine_id } = data;

    this.setState({
      nodes,
      links,
      subroutine_id
    })
  }

  componentDidMount() {
    const graph_id = this.props.match.params.id || 1;
    this.fetchData(graph_id);
  }

  componentWillReceiveProps(nextProps) {
    this.fetchData(nextProps.match.params.id);
    this.panTo(0, 0);
  }

  fetchData(id) {
    axios.get(`/api/v1/graph?id=${id}&stops=1`)
      .then(res => {
        this.updateGraph(res.data);
      });
  }

  render() {
    const paperStyle = {
      width: '100%',
      height: '100vh',
      margin: 0,
      padding: 0,
    };
    const flexStyle = {
      flex: 1,
    };
    const graph_id = this.props.match.params.id || 1;

    return (
      <div style={paperStyle} id="mainPaper">
        <DraggableCore onDrag={this.handleDrag}>
          <svg width="100%" height="100%">
              <defs>
                <NormalArrow id="markerArrow" />
                <NormalArrow id="markerRedArrow" style={{ fill: 'red', stroke: 'none' }} />
                <linearGradient id="gradientGreen" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor="green"/>
                  <stop offset="100%" stopColor="black"/>
                </linearGradient>
                <linearGradient id="gradientPurple" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%" stopColor="purple"/>
                  <stop offset="100%" stopColor="black"/>
                </linearGradient>
              </defs>
              <Graph ref={(drawing) => this.drawing.ref = drawing} rankdir="LR">
                { this.state.nodes.map(node => (
                  <Rect key={node.id} id={node.id} node={node.id} data-id={node.id} data-subroutine-id={node.subroutine_id} data-is-symbol={node.is_symbol} data-has-more={Number(node.has_more)} data-is-copy={node.is_copy}
                    rx={5} ry={5}
                    style={{
                      fill: node.has_more ? (node.is_symbol ? "url(#gradientPurple)" : "url(#gradientGreen)") : (node.is_symbol ? 'purple' : 'green'),
                      stroke: node.id == graph_id ? 'red' : 'none',
                      opacity: node.is_copy ? 0.5 : 1.0
                    }}
                    onClick={this.handleNodeClick}
                  >
                    <text fontSize={8} fill="white">
                      { node.label }
                    </text>
                  </Rect>
                ))}
                { this.state.links.map(link => (
                  <Edge key={link.id} id={link.id} markerEnd={
                      link.xref == 1 ? "url(#markerArrow)" : "url(#markerRedArrow)"
                    }
                    source={link.source_id} target={link.target_id}
                    style={{
                      stroke: (link.xref == 1 ? 'black' : 'red')
                    }}
                  />
                )) }
              </Graph>
          </svg>
        </DraggableCore>
        <Drawer
          anchor="right"
          type="persistent"
          open={true}
        >
          <SubroutineInfo subroutine_id={ this.state.subroutine_id } />
        </Drawer>
      </div>
    );
  }

  panTo(x, y) {
    this.drawing.panX = x > 0 ? 0 : x;
    this.drawing.panY = y > 0 ? 0 : y;
    var el = ReactDOM.findDOMNode(this.drawing.ref);
    el.setAttribute("transform", `translate(${this.drawing.panX}, ${this.drawing.panY})`);
  }

  handleDrag = (e, data) => {
    this.panTo(this.drawing.panX + data.deltaX,
      this.drawing.panY + data.deltaY);
  }

  handleNodeClick = (e) => {
    const id = e.currentTarget.dataset.id;

    var state = {};

    this.props.history.push(`/map/${id}`);
  }
}

export default MapPage;
