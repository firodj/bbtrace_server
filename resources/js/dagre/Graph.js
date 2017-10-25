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

type Props = {
  children?: React.ChildrenArray<React.Element<any>>
}

type State = {
  ready: boolean
}

/**
 * get bounding box which must be rendering on temp DOM
 */
function getBBox(child) {
  const el = <g className="label">{ child.props.children }</g>;
  debugger;
  ReactDOM.render(
    el,
    document.getElementById('bboxlabel')
  );
  // return {x: 0, y: 0, width: 0, height: 0};
  
  var g = d3.select('svg#bboxlabel g.label');
  window.g = g;
  debugger;

  var labelBBox = g.node().getBBox();
  console.log(labelBBox);

  return {
    x: labelBBox.x,
    y: labelBBox.y,
    width: labelBBox.width,
    height: labelBBox.height
  };
}

/**
 * Serialize input graph to be compared later
 */
function serializeGraph(g) {
  const packed = [];
  g.nodes().forEach(function(n) {
    var node = g.node(n);
    packed.push([n, node.width, node.height, node.labelBBox]);
  });

  g.edges().forEach(function(e) {
    var edge = g.edge(e);
    packed.push([e.v, e.w, edge.labelBBox]);
  });

  return packed;
}

class Graph extends React.Component<Props> {
  nodes = []
  edges = []
  gLast = {
    serialize: [], nodes: {}, edges: {}, graph: {}
  }
  graph: ?Object = null;

  getChildContext() {
    return {
      graph: this.graph
    };
  }

  createGraph() {
    // Create a new directed graph
    const graph = new graphlib.Graph();

    // Set an object for the graph label
    graph.setGraph({
      marginx: 20, marginy: 20,
      nodesep: 10, edgesep: 2, ranksep: 20,
      rankdir: 'LR',
      align: 'DR',
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
    const graph = this.graph;

    if (graph.dirty) {
      layout(graph);
      graph.dirty = false;
      this.graph = graph;

      console.log('relayout');
      this.forceUpdate();
    }
  }

  calculateLayout(props: Props) {
    // Create a new directed graph
    var g = new graphlib.Graph();

    // Set an object for the graph label
    g.setGraph({ marginx: 20, marginy: 20,
      nodesep: 10, edgesep: 2, ranksep: 20,
      rankdir: 'LR',
      align: 'DR',
    });

    // Default to assigning a new object as a label for each new edge.
    g.setDefaultEdgeLabel(function() { return {}; });

    var { children } = props;

    if (children !== undefined) {
      React.Children.forEach(children, child => {
        var { width, height, labelpos } = child.props;
        const labelProps = {
          label: child,
          labelBBox: {x:0, y:0, width:0, height:0},
          width,
          height,
          labelpos,
        };

        if (child.props.children !== undefined) {
          const labelBBox = getBBox(child);
          labelProps.labelBBox = labelBBox;
          labelProps.width = width || (labelBBox.width + 10);
          labelProps.height = height ||(labelBBox.height + 10);
        }

        if (child.type === Edge) {
          g.setEdge(child.props.source, child.props.target, labelProps);
        } else { // Rect
          g.setNode(child.key, labelProps);
        }
      });
    }

    const gNow = serializeGraph(g);

    if (! _.isEqual(gNow, this.gLast.serialize)) {
      // Calculate Layout
      console.log('Dagre layout');
      layout(g);

      const gLast = {
        serialize: gNow,
        nodes: {},
        edges: {},
        graph: {
          width: g.graph().width,
          height: g.graph().height
        }
      };

      g.nodes().forEach(function(n) {
        var node = g.node(n);
        gLast.nodes[n] = {
          width: node.width,
          height: node.height,
          x: node.x,
          y: node.y,
          labelBBox: node.labelBBox
        };
      });

      g.edges().forEach(function(e) {
        var edge = g.edge(e);
        const key = `${e.v}-${e.w}`
        gLast.edges[key] = {
          points: edge.points,
          x: edge.x,
          y: edge.y,
          labelBBox: edge.labelBBox,
          key
        };
      });

      this.gLast = gLast;
    }

    var nodes = g.nodes().map(n => {
      var node = g.node(n);
      return React.cloneElement(node.label, this.gLast.nodes[n]);
    });

    var edges = g.edges().map(e => {
      var edge = g.edge(e);
      const key = `${e.v}-${e.w}`
      return React.cloneElement(edge.label, this.gLast.edges[key]);
    });

    this.nodes = nodes;
    this.edges = edges;
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

  componentDidUpdate(prevProps: Props, prevState: State) {
    this.relayout();
  }
}

Graph.childContextTypes = {
  graph: PropTypes.object
}

export default Graph;
