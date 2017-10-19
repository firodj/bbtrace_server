/* @flow */
import React, { Component } from 'react';

import * as d3 from 'd3';
import _ from 'lodash';
import graphlib from 'ciena-graphlib';
import dagreD3 from 'ciena-dagre-d3';

type Props = {};

class DagrePage extends Component<Props> {
  componentDidMount()
  {
    // Create a new directed graph
    var g = new graphlib.Graph();

    // Set an object for the graph label
    g.setGraph({ marginx: 20, marginy: 20 });

    // Default to assigning a new object as a label for each new edge.
    g.setDefaultEdgeLabel(function() { return {}; });

    g.setNode(1, { label: 1, width: 200, height: 200, style: { strokeWidth: 1.5} });
    g.setNode(2, { label: 2, width: 200, height: 200, style: {fill: 'none', stroke: 'red'} });
    g.setNode(3, { label: 3, width: 200, height: 200, style: {fill: 'none', stroke: 'blue'} });

    g.setEdge(2, 1, { label: 'Yes', style: { stroke: "black" } });
    g.setEdge(2, 3, { style: { stroke: "black" }, curve: d3.curveBasis });

    var svg = d3.select("#mainPaper svg")
                .append('g');

    var zoom = d3.zoom()
              .scaleExtent([1, 1])
              .on("zoom", () => {
                svg.attr("transform", d3.event.transform); //"translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
              });

    d3.select("#mainPaper svg")
            .call(zoom)
              .on("dblclick.zoom", null);

    var render = dagreD3.render();

    g.graph().transition = function(selection) {
      return selection.transition().duration(500);
    };

    svg.call(render, g);
  }

  render() {
    const styles = {
      paper: {
        width: '100%',
        height: '100vh',
        margin: 0,
        padding: 0,
        background: 'white',
      },
      flex: {
        flex: 1,
      }
    };

    return (
      <div style={styles.paper} id="mainPaper">
        <svg width="100%" height="100%">
        </svg>
      </div>
    )
  }

}

export default DagrePage;
