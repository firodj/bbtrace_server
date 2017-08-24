import React from 'react';

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

import BlockInfo from './BlockInfo';
import FunctionInfo from './FunctionInfo';
import { orangeAvatar } from './colors';
import Avatar from 'material-ui/Avatar';
import ListBlocks from './ListBlocks';
import ListFunctions from './ListFunctions';

const sprintf = require('sprintf-js').sprintf;

import axios from 'axios';

class Root extends React.Component {
  constructor(props) {
    super(props);
    this.handleBlockClick = this.handleBlockClick.bind(this);
    this.handleMoreClick = this.handleMoreClick.bind(this);
    this.handlePrevClick = this.handlePrevClick.bind(this);
    this.handleRightOpen = this.handleRightOpen.bind(this);
    this.handleRightClose = this.handleRightClose.bind(this);
    this.handleMenuClick = this.handleMenuClick.bind(this);
    this.handleFunctionClick = this.handleFunctionClick.bind(this);
    this.handleFunctionStepperClick = this.handleFunctionStepperClick.bind(this);

    this.state = {
      blocks: [],
      functions: {
        items: [],
        steps: 0,
        activeStep: 0,
      },
      info: {
        type: null,
      },
      offset: 0,
      hasPrev: false,
      hasMore: true,
      open: {
        right: false,
      },
      activePage: 'blocks',
      graph: {
        nodes: [],
        links: [],
      },
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
      }

      that.setState({
        open: { right: selected }
      });
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

  handleBlockClick(id) {
    axios.get(`/api/v1/block/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleFunctionClick(id) {
    axios.get(`/api/v1/function/${id}`)
      .then(res => {
        this.setState({ info: res.data });
      });
  }

  handleMoreClick() {
    if (this.state.hasMore) {
      this.appendBlocks(this.state.offset + this.props.limit);
    }
  }

  handlePrevClick() {
    if (this.state.hasPrev) {
      this.appendBlocks(this.state.offset - this.props.limit);
    }
  }

  handleRightClose() {
    this.setState({
      open: { right: false }
    });
  }
  handleRightOpen() {
    this.setState({
      open: { right: true }
    });
  }

  handleMenuClick(item)
  {
    this.setState({ activePage: item, info: { type: null } });
  }

  handleFunctionStepperClick(direction)
  {
    this.appendFunctions(this.state.functions.activeStep + direction);
  }

  appendBlocks(offset, limit) {
    axios.get('/api/v1/blocks', {
      params: { offset, limit }
    })
      .then(res => {
        const data = res.data;
        this.setState({
          offset: data.offset,
          hasMore: data.hasMore,
          hasPrev: data.hasPrev,
          blocks: data.blocks, // this.state.blocks.concat(data.blocks) 
        });
      });
  }

  appendFunctions(activeStep) {
    axios.get('/api/v1/functions', {
      params: { activeStep }
    })
      .then(res => {
        const data = res.data;
        this.setState({
          functions: data, // this.state.blocks.concat(data.blocks) 
        });
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
          <Drawer
            docked={true}
            anchor="right"
            open={this.state.open.right}>
            <Paper style={{width: 400}}>
            </Paper>
          </Drawer>
          <svg width="100%" height="100%"></svg>
        </div>
    );
  }

  render2() {
    const listStyle = {
      overflowY: 'auto',
      height: '100vh',
      padding: 0,
    };

    const menuStyle = {
      width: 250,
      flex: 'initial',
    };

    const flexStyle = {
      flex: 1,
    };

    return (
      <div>
        <AppBar position="static">
          <Toolbar>
            <Typography type="title" color="inherit" style={flexStyle}>
              { this.props.title }
            </Typography>
            <IconButton color="contrast" aria-label="Menu" onClick={this.handleRightOpen}>
              <MenuIcon />
            </IconButton>
          </Toolbar>
        </AppBar>
        <Grid container gutter={0}>
          <Grid item xs={3}>
            { this.state.activePage == 'blocks' && <List style={listStyle} dense={true} disablePadding={true}>
              { this.state.hasPrev && <ListButton primary="Prev" onClick={this.handlePrevClick} /> }
              <ListBlocks blocks={this.state.blocks} onClick={this.handleBlockClick} />
              { this.state.hasMore && <ListButton primary="More" onClick={this.handleMoreClick} /> }
            </List> }
            { this.state.activePage == 'functions' &&
              <ListFunctions functions={this.state.functions.items}
                onClick={this.handleFunctionClick}
                steps={this.state.functions.steps}
                activeStep={this.state.functions.activeStep}
                onStepperClick={this.handleFunctionStepperClick}
                />
            }
          </Grid>
          <Grid item xs={9}>
            { this.state.info.type == 'block' && <BlockInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
            { this.state.info.type == 'function' && <FunctionInfo info={this.state.info} onBlockClick={this.handleBlockClick}/> }
          </Grid>
        </Grid>
        <Drawer
          anchor="right"
          open={this.state.open.right}
          onRequestClose={this.handleRightClose}
          onClick={this.handleRightClose}>
          <List style={menuStyle} disablePadding>
            <ListMenus onMenuClick={ this.handleMenuClick }/>
          </List>
        </Drawer>
      </div>
    );
  }
}

Root.defaultProps = {
  limit: 100,
  title: window.env.name,
};

const smallAvatar = {
  width: 20,
  height: 20,
};

const ListButton = ({ primary, onClick }) =>
    <ListItem button onClick={onClick}>
      <ListItemText primary={primary} />
    </ListItem>
;

const ListMenus = ({ onMenuClick }) => <div>
    <ListItem button onClick={ () => onMenuClick('blocks') }>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Blocks" />
    </ListItem>
    <ListItem button onClick={ () => onMenuClick('functions') }>
      <ListItemIcon>
        <StarBorder />
      </ListItemIcon>
      <ListItemText primary="Functions" />
    </ListItem>
  </div>
;

export default Root;
