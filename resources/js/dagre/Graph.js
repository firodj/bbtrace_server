/* @flow */

import * as React from 'react';
import _ from 'lodash';
import ReactDOM from 'react-dom';
import PropTypes from 'prop-types';

import Rect from './Rect';
import Edge from './Edge';

import { layout } from 'ciena-dagre';
import graphlib from 'ciena-graphlib';

import * as d3 from 'd3';

type RankDirType = 'TB' | 'BT' | 'LR' | 'RL';
type RankAlignType = 'UL' | 'UR' | 'DL' | 'DR';

type Props = {
  children?: React.ChildrenArray<React.Element<any>>,
  rankdir: RankDirType,
  rankalign: RankAlignType,
}

class Graph extends React.Component<Props> {
  static defaultProps = {
    rankdir: 'TB',
    rankalign: 'DR',
  }

  nodes = []
  edges = []
  gLast = {
    serialize: [], nodes: {}, edges: {}, graph: {}
  }
  graph: ?any = null;

  getChildContext() {
    return {
      graph: this.graph
    };
  }

  createGraph() {
    // Create a new directed graph
    const graph: any = new graphlib.Graph();

    // Set an object for the graph label
    graph.setGraph({
      marginx: 20, marginy: 20,
      nodesep: 10, edgesep: 2, ranksep: 20,
      rankdir: this.props.rankdir,
      align: this.props.rankalign,
    });

    // Default to assigning a new object as a label for each new edge.
    graph.setDefaultEdgeLabel(function() { return {}; });

    this.graph = graph;
    this.graph.dirty = false;
  }

  componentWillMount() {
    this.createGraph();
  }

  relayout() {
    const graph: any = this.graph;

    if (graph.dirty) {
      layout(graph);
      graph.dirty = false;
      this.graph = graph;

      console.log('relayout');
      this.forceUpdate();
    }
  }

  render() {
    var { children, ...props } = this.props;

    return (
      <g className="output" {...props}>
        { children }
      </g>
    );
  }

  componentDidMount() {
    this.relayout();
  }

  componentDidUpdate(prevProps: Props) {
    this.relayout();
  }
}

Graph.childContextTypes = {
  graph: PropTypes.object
}

export default Graph;
