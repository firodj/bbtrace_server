/* @flow */

import * as React from 'react';
import PropTypes from 'prop-types';
import * as d3 from 'd3';
import type { BBoxType } from '../exports';

type Props = {
  node: string,
  width?: number,
  height?: number,
  children: React.Node,
  style: Object,
  rx?: number,
  ry?: number,
}

const LABEL_MARGIN = 10;

class Rect extends React.Component<Props> {
  labelRef: ?Element = null;

  static defaultProps = {
    style: {},
  }

  labelBBox: ?BBoxType;

  render() {
    var {
      rx,
      ry,
      width,
      height,
      children,
      style,
      node,
      ...props
    } = this.props;

    var x = 0;
    var y = 0;

    const nodeLabel = this.context.graph.node(node);
    var labelBBox = null;
    if (nodeLabel !== undefined) {
      width = nodeLabel.width;
      height = nodeLabel.height;
      x = nodeLabel.x;
      y = nodeLabel.y;
      labelBBox = nodeLabel.labelBBox;
    }

    width = width || 0;
    height = height || 0;

    var labelTransform = null;
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
    graph.dirty = true;

    // console.log('Rect mounted:', node);
  }

  componentDidUpdate() {
    const graph = this.context.graph;
    var { width, height, node } = this.props;
    const labelProps = graph.node(node);
    const g = d3.select(this.labelRef);
    const labelBBox = g.node().getBBox();

    width = width || labelBBox.width + LABEL_MARGIN;
    height = height || labelBBox.height + LABEL_MARGIN;

    if (width != labelProps.width ||
      height != labelProps.height)
    {
      const graph = this.context.graph;
      graph.dirty = true;

      const nextLabelProps = {
        labelBBox,
        width,
        height,
      };
      graph.setNode(node, nextLabelProps);
      graph.dirty = true;

      // console.log('Rect updated:', node);
    }
  }

  componentWillUnmount() {
    const { node } = this.props;
    const graph = this.context.graph;
    graph.removeNode(node);
    graph.dirty = true;

    // console.log('Rect unmount:', node);
  }
}

Rect.contextTypes = {
  graph: PropTypes.object
}

export default Rect;
