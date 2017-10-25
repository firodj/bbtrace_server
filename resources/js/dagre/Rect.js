/* @flow */

import * as React from 'react';
import PropTypes from 'prop-types';
import * as d3 from 'd3';

type Props = {
  node: string,
  x: number,
  y: number,
  width?: number,
  height?: number,
  children: React.Node,
  style: Object,
  labelBBox?: Object,
  rx?: number,
  ry?: number,
}

class Rect extends React.Component<Props> {
  labelRef: ?SVGGElement = null;
  labelBBox: ?SVGRect = null;

  static defaultProps = {
    x: 0,
    y: 0,
    style: {},
  }

  render() {
    var {
      x,
      y,
      rx,
      ry,
      width,
      height,
      children,
      style,
      node,
      ...props
    } = this.props;

    const nodeLabel = this.context.graph.node(node);
    if (nodeLabel !== undefined) {
      width = nodeLabel.width;
      height = nodeLabel.height;
      x = nodeLabel.x;
      y = nodeLabel.y;
    }

    width = width || 0;
    height = height || 0;

    var labelTransform = null;
    const labelBBox = this.labelBBox;
    if (labelBBox !== null) {
      var labelX = labelBBox.x + width / 2 - labelBBox.width / 2;
      var labelY = -labelBBox.y + height / 2 - labelBBox.height / 2;
      labelTransform="translate(" + labelX + "," + labelY + ")";
    }
    return (
    <g transform={"translate("+
       ( x - (width / 2)) + ","+
       ( y - (height / 2)) + ")"}
      {...props} >
      <rect width={width} height={height} rx={rx} ry={ry} style={style} />
      <g className="label" transform={labelTransform} ref={(labelRef) => { this.labelRef = labelRef; }}>
        { children }
      </g>
    </g>
    );
  }

  componentDidMount() {
    const g = d3.select(this.labelRef);
    const LABEL_MARGIN = 10;

    const { width, height, node } = this.props;
    const labelBBox = g.node().getBBox();
    this.labelBBox = labelBBox;

    const labelProps = {
      labelBBox,
      width: width || (labelBBox.width + LABEL_MARGIN),
      height: height || (labelBBox.height + LABEL_MARGIN),
    };

    const graph = this.context.graph;
    graph.setNode(node, labelProps);
  }
}

Rect.contextTypes = {
  graph: PropTypes.object
}

export default Rect;
