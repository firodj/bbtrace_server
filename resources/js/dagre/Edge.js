/* @flow */

import * as React from 'react';
import * as d3 from 'd3';

type Props = {
  points: Array<any>,
  style: Object,
  source: string,
  target: string,
  children?: React.Node,
  labelBBox?: Object,
}

class Edge extends React.Component<Props> {
  static defaultProps = {
    points: [],
    style: {
      stroke: 'black',
      fill: 'none'
    }
  }

  render() {
    var {
      points,
      source,
      target,
      style,
      labelBBox,
      children,
      ...props
    } = this.props;

    var labelTransform = null;
    if (labelBBox !== undefined) {
      var labelX = labelBBox.x + points[1].x - labelBBox.width / 2;
      var labelY = -labelBBox.y + points[1].y - labelBBox.height / 2;
      labelTransform="translate(" + labelX + "," + labelY + ")";
    }
    var line = d3.line()
      .x(function(d) { return d.x; })
      .y(function(d) { return d.y; });

    line.curve(d3.curveBasis);
    var path = line(points);

    return (
      <g>
        <path {...props} d={path} style={style} />;
        <g className="label" transform={labelTransform}>
          { children }
        </g>
      </g>
    );
  }
}

export default Edge;
