/* @flow */

import * as React from 'react';
import _ from 'lodash';
import ReactDOM from 'react-dom';

import Rect from './Rect';
import Edge from './Edge';

import { layout } from 'ciena-dagre';
import graphlib from 'ciena-graphlib';

import * as d3 from 'd3';

type Props = {
  children?: React.ChildrenArray<React.Element<any>>
}

/**
 * get bounding box which must be rendering on temp DOM
 */
function getBBox(child) {
  ReactDOM.render(
    child,
    document.getElementById('temp')
  );
  var g = d3.select('svg#temp g.label');
  var labelBBox = g.node().getBBox();

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

  componentWillMount() {
    this.calculateLayout(this.props);
  }

  componentWillUpdate(nextProps: Props)
  {
    this.calculateLayout(nextProps);
  }

  calculateLayout(props: Props) {
    // Create a new directed graph
    var g = new graphlib.Graph();

    // Set an object for the graph label
    g.setGraph({ marginx: 20, marginy: 20 });

    // Default to assigning a new object as a label for each new edge.
    g.setDefaultEdgeLabel(function() { return {}; });

    var { children } = props;

    if (children !== undefined) {
      React.Children.forEach(children, child => {
        if (child.type === Edge) {
          const labelBBox = getBBox(child);
          g.setEdge(child.props.source, child.props.target, {
            label: child,
            labelBBox,
          });
        } else { // Rect
          var { width, height } = child.props;
          const labelBBox = getBBox(child);

          width = width || (labelBBox.width + 10);
          height = height ||(labelBBox.height + 10);

          g.setNode(child.key, {
            label: child,
            width,
            height,
            labelBBox,
          });
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
        { this.nodes }
        { this.edges }
      </g>
    );
  }
}

export default Graph;
