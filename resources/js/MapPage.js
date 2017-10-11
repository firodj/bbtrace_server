import React, { Component } from 'react';

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
import { bboxCollide } from 'd3-bboxCollide';

import SubroutineInfo from './SubroutineInfo';

const sprintf = require('sprintf-js').sprintf;

import axios from 'axios';

class MapPage extends Component {

  constructor(props) {
    super(props);

    this.state = {
      info: {
        id: 0
      },
      open: {
        right: false,
      },
      graph: {
        nodes: [],
        links: [],
      },
      subroutine_id: 0
    };

    this.drawing = {};
  }

  initGraph()
  {
    var that = this;

    this.drawing.ticked = function() {
      that.drawing.link
          .attr("x1", function(d) { return d.source.x; })
          .attr("y1", function(d) { return d.source.y; })
          .attr("x2", function(d) { return d.target.x > d.source.x ? d.target.x - 10 : d.target.x + 10; })
          .attr("y2", function(d) { return d.target.y > d.source.y ? d.target.y - 10 : d.target.y + 10; });

      that.drawing.node
          .attr("x", function(d) { return d.x - d.label.length * 3.5; })
          .attr("y", function(d) { return d.y - 7.5; });

      that.drawing.text
          .attr("x", function(d) { return d.x; })
          .attr("y", function(d) { return d.y; });
    };

    this.drawing.dragstarted = function(d) {
      if (!d3.event.active) that.drawing.simulation.alphaTarget(0.3).restart();
      d.fx = d.x;
      d.fy = d.y;
    };

    this.drawing.dragged = function(d) {
      d.fx = d3.event.x;
      d.fy = d3.event.y;
    }

    this.drawing.dragended = function(d) {
      if (!d3.event.active) that.drawing.simulation.alphaTarget(0);
      d.fx = null;
      d.fy = null;
    }

    this.drawing.dblclicked = function(d) {
      if (!d.stopped) return;

      axios.get(`/api/v1/graph?id=${d.id}`)
        .then(res => {
          that.updateGraph(res.data);
        });
    }

    this.drawing.clicked = function(d) {
      if (d3.event.defaultPrevented) return; // dragged

      const selected = !d3.select(this).classed("selected");

      that.drawing.node.classed("selected", false);
      that.drawing.link.classed("selected", false);

      d3.select(this).classed("selected", function(d) {
        return selected;
      });

      if (selected) {
        that.drawing.link.classed("selected", function(l) {
          const s_id = typeof l.source === 'object' ? l.source.id : l.source;
          const t_id = typeof l.target === 'object' ? l.target.id : l.target;
          return (s_id == d.id || t_id == d.id);
        });

        if (d.is_symbol == 0) {
          that.setState({
            subroutine_id: d.subroutine_id,
            open: { right: true }
          });
        } else {
          that.setState({
            open: { right: false }
          });
        }
      } else {
        that.setState({
          open: { right: false }
        });
      }
    };

    var zoom = d3.zoom()
              .scaleExtent([1, 1])
              .on("zoom", () => {
                that.drawing.svg.attr("transform", d3.event.transform); //"translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
              });

    var width = document.getElementById('mainPaper').offsetWidth;
    var height = document.getElementById('mainPaper').offsetHeight;

    this.drawing.svg = d3.select("#mainPaper svg")
            .append('g');

    var defs = d3.select("#mainPaper svg").append("defs");

    defs.append("marker")
        .attr("id", "arrow")
        .attr("viewBox", "0 -5 10 10")
        .attr("refX", 5)
        .attr("refY", 0)
        .attr("markerWidth", 4)
        .attr("markerHeight", 4)
        .attr("orient", "auto")
      .append("path")
        .attr("d", "M0,-5L10,0L0,5")
        .style("stroke", "gray");

    d3.select("#mainPaper svg")
            .call(zoom)
              .on("dblclick.zoom", null);
    d3.select("#mainPaper svg")
            .call(zoom.translateBy, width/2, height/2);

    this.drawing.linkContainer = this.drawing.svg.append("g")
        .attr("class", "links");
    this.drawing.nodeContainer = this.drawing.svg.append("g")
        .attr("class", "nodes");
    this.drawing.textContainer = this.drawing.svg.append("g")
        .attr("class", "labels");

    const collide = bboxCollide(function (d,i) {
      return [[- d.label.length * 3.5,  - 7.5],[+ d.label.length * 3.5, + 7.5]]
    });

    this.drawing.simulation = d3.forceSimulation()
      .force("charge", d3.forceManyBody())
      .force("link", d3.forceLink()
        .id(function(d) { return d.id; })
        .distance(250).strength(.1)
      )
      .force("center", d3.forceCenter(0, 0))
      .force("collide", collide.strength(.1).iterations(4))
      .stop()
      .on("tick", this.drawing.ticked)
  }

  beforeNodes(mergeGraph)
  {
    const changeNodeById = d3.map(
      _.filter(mergeGraph.nodes, node => !node.stopped),
      d => d.id);
    const removeNodes = [];

    _.forEach(this.state.graph.nodes, oldNode => {
      const updateNode = changeNodeById.get(oldNode.id);
      if (updateNode) {
        _.assign(oldNode, updateNode);
        removeNodes.push(oldNode);
      }
    });

    const removeNodeById = d3.map(removeNodes, d => d.id);

    _.remove(mergeGraph.nodes, oldNode => removeNodeById.get(oldNode.id));
  }

  updateGraph(mergeGraph)
  {
    this.beforeNodes(mergeGraph);

    var graph = {
        nodes: this.state.graph.nodes.concat(mergeGraph.nodes),
        links: this.state.graph.links.concat(mergeGraph.links),
    }

    this.setState({ graph });

    this.drawing.simulation.stop();

    var node = this.drawing.nodeContainer
      .selectAll("rect")
      .data(graph.nodes, function(d) { return d.id; });
    node.exit().remove();

    this.drawing.node = node
      .enter().append("rect")
        .attr("width", function(d) { return d.label.length * 7; })
        .attr("height", 15)
        .attr("rx", 3)
        .attr("ry", 3)
        .style("fill", function(d) {
          return d.is_symbol ? (d.is_copy ? "magenta" : "purple") :
            (d.is_copy ? "lime" : "green");
        })
        .classed("is_copy", function(d) {
          return d.is_copy;
        })
        .on("click", this.drawing.clicked)
        .on("dblclick", this.drawing.dblclicked)
        .call(d3.drag()
          .on("start", this.drawing.dragstarted)
          .on("drag", this.drawing.dragged)
          .on("end", this.drawing.dragended))
      .merge(node)
        .style("fill-opacity", function(d) {
          return d.stopped ? .3 : (d.is_copy ? .7 : 1);
        });

    var link = this.drawing.linkContainer
      .selectAll("line")
      .data(graph.links, function(d) { return d.id; });
    link.exit().remove();
    this.drawing.link = link
      .enter().append("line")
        .style("stroke-width", 1.5)
        .style("stroke", function(d) {
          return d.xref == 0 ? 'dimgray' : 'lightgray';
        })
        .attr("marker-end", "url(#arrow)")
      .merge(link);

    var text = this.drawing.textContainer
      .selectAll("text")
      .data(graph.nodes, function(d) { return d.id; });
    text.exit().remove();
    this.drawing.text = text
      .enter().append("text")
        .attr("dy", 2)
        .attr("text-anchor", "middle")
        .text(function(d) {return d.label})
        .attr("fill", function(d) {
          return d.is_copy ? "black" : "white";
        })
      .merge(text);

    this.drawing.simulation.nodes(graph.nodes);
    this.drawing.simulation.force("link").links(graph.links);
    this.drawing.simulation.restart();
  }

  componentDidMount() {
    this.initGraph();
    // https://bl.ocks.org/mbostock/6123708
    axios.get(`/api/v1/graph`)
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

    return (
      <div style={paperStyle} id="mainPaper">
        <svg width="100%" height="100%"></svg>
        <Drawer
          anchor="right"
          type="persistent"
          open={this.state.open.right}
        >
          <SubroutineInfo subroutine_id={ this.state.subroutine_id } />
        </Drawer>
      </div>
    );
  }
}

export default MapPage;
