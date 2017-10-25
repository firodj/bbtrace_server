/* @flow */

import * as React from 'react';
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
  graph?: Object,
}

class Rect extends React.Component<Props> {
  labelRef: ?SVGGElement = null;

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
      labelBBox,
      children,
      style,
      graph,
      node,
      ...props
    } = this.props;

    var labelTransform = null;
    width = width || 0;
    height = height || 0;

    if (labelBBox !== undefined) {
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
    const labelProps = {
      labelBBox,
      width: width || (labelBBox.width + LABEL_MARGIN),
      height: height || (labelBBox.height + LABEL_MARGIN),
    };

    const graph = this.props.graph;
    graph.setNode(node, labelProps);
  }
}

export default Rect;

