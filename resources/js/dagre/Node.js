/* @flow */

import * as React from 'react';

type Props = {
  x: number,
  y: number,
  width?: number,
  height?: number,
  children: React.Node,
  style: Object,
  labelBBox?: Object,
}

class Node extends React.Component<Props> {
  static defaultProps = {
    x: 0,
    y: 0,
    style: {},
  }

  render() {
    var {
      x,
      y,
      width,
      height,
      labelBBox,
      children,
      style,
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
      <rect width={width} height={height} style={style} />
      <g className="label" transform={labelTransform}>
        { children }
      </g>
    </g>
    );
  }
}

export default Node;

