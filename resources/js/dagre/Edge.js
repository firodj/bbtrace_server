/* @flow */

import * as React from 'react';
import PropTypes from 'prop-types';
import * as d3 from 'd3';

type Props = {
  source: string,
  target: string,
  x: number,
  y: number,
  points: Array<any>,
  style: Object,
  children?: React.Node,
  labelBBox?: Object,
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
      x,
      y,
      points,
      source,
      target,
      style,
      children,
      labelpos,
      ...props
    } = this.props;

    const edgeLabel = this.context.graph.edge({v: source, w: target});

    if (edgeLabel !== undefined) {
      points = edgeLabel.points;
      x = edgeLabel.x || 0;
      y = edgeLabel.y || 0;
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

    const { source, target, labelpos } = this.props;
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
  }
}

Edge.contextTypes = {
  graph: PropTypes.object
}

export default Edge;
