/* @flow */

import * as React from 'react';
import PropTypes from 'prop-types';
import * as d3 from 'd3';

type Props = {
  source: string,
  target: string,
  style: Object,
  children?: React.Node,
  labelpos?: "l" | "c" | "r",
}

class Edge extends React.Component<Props> {
  static defaultProps = {
    points: [],
    style: {
      stroke: 'black',
      fill: 'none'
    },
    x: 0,
    y: 0,
    labelpos: "r",
  }

  labelRef: ?SVGGElement = null;
  labelBBox: ?SVGRect = null;

  render() {
    var {
      source,
      target,
      style,
      children,
      labelpos,
      ...props
    } = this.props;

    const edgeLabel = this.context.graph.edge({v: source, w: target});

    var x = 0;
    var y = 0;
    var points = [];

    if (edgeLabel !== undefined) {
      points = edgeLabel.points;
      x = edgeLabel.x || x;
      y = edgeLabel.y || y;
    }

    var labelTransform = null;
    const labelBBox = this.labelBBox;

    if (labelBBox !== null) {
      var labelX = labelBBox.x + x - labelBBox.width / 2;
      var labelY = -labelBBox.y + y - labelBBox.height / 2;
      labelTransform="translate(" + labelX + "," + labelY + ")";
    }
    var line = d3.line()
      .x(function(d) { return d.x; })
      .y(function(d) { return d.y; });

    line.curve(d3.curveBasis);
    var path = line(points);

    if (style.fill === undefined) style.fill = 'none';

    return (
      <g>
        <path {...props} d={path} style={style} />
        <g className="label" transform={labelTransform} ref={(labelRef) => { this.labelRef = labelRef; }}>
          { children }
        </g>
      </g>
    );
  }

  componentDidMount() {
    const g = d3.select(this.labelRef);
    const LABEL_MARGIN = 10;

    const { id, source, target, labelpos } = this.props;
    const labelBBox = g.node().getBBox();
    this.labelBBox = labelBBox;

    const labelProps = {
      labelBBox,
      width: labelBBox.width,
      height: labelBBox.height,
      labelpos,
    };

    const graph = this.context.graph;

    graph.setEdge(source, target, labelProps);
    graph.dirty = true;
  }

  componentDidUpdate() {
    const graph = this.context.graph;
    const { id, source, target, labelpos } = this.props;
    const labelProps = graph.edge({v: source, w: target});
    const g = d3.select(this.labelRef);
    const labelBBox = g.node().getBBox();

    var width = labelBBox.width;
    var height = labelBBox.height;

    const nextLabelProps = {
      labelBBox,
      width: labelBBox.width,
      height: labelBBox.height,
      labelpos,
    };

    if (! labelProps) {
      console.log('Edge miss updated: #', id, source, target);
    } else if (width != labelProps.width ||
      height != labelProps.height)
    {
      graph.setEdge(source, target, nextLabelProps);
      graph.dirty = true;
      // console.log('Edge changes: #', id, source, target);
    }
  }

  componentWillUnmount() {
    const { id, source, target } = this.props;
    const graph = this.context.graph;
    graph.removeEdge(source, target);
    graph.dirty = true;

    // console.log('Edge unmount: #', id, source, target);
  }
}

Edge.contextTypes = {
  graph: PropTypes.object
}

export default Edge;
