/* @flow */
import React, { Component } from 'react';

import * as d3 from 'd3';
import _ from 'lodash';
import { Graph, Rect, Edge, NormalArrow } from './dagre';

type Props = {};
type State = {
  offsetX: number,
  offsetY: number
}

class DagrePage extends Component<Props, State> {
  lastMousePos = {x: 0, y: 0, dragging: false}

  state = {
    offsetX: 0,
    offsetY: 0
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
        <svg width="100%" height="100%"
          onMouseDown={this.handlePanning}
          onMouseUp={this.handlePanning}
          onMouseMove={this.handlePanning}
        >
          <defs>
            <NormalArrow id="markerArrow" />
          </defs>
          <g transform={`translate(${this.state.offsetX},${this.state.offsetY})`}>
            <Graph>
              <Rect key="foo" style={{ fill: 'red' }} onClick={(e) => console.log(e)}>
                <text fontSize="10" fontFamily="Verdana">
                  <tspan x="0" y="0">Here is a paragraph that</tspan>
                  <tspan x="0" y="10">requires word wrap.</tspan>
                </text>
              </Rect>
              <Rect key="bar" width={100} height={150} style={{ fill: 'green' }}>
                <text>
                  bar
                </text>
              </Rect>
              <Rect key="baz" width={100} height={150} style={{ fill: 'blue' }}>
                <text>
                  baz
                </text>
              </Rect>
              <Edge markerEnd="url(#markerArrow)" source="foo" target="bar">
                <text>
        { this.state.offsetX }
                </text>
              </Edge>
              <Edge markerEnd="url(#markerArrow)" source="foo" target="baz" />
            </Graph>
          </g>
        </svg>
      </div>
    )
  }

  handlePanning = (e: SyntheticMouseEvent<HTMLElement>) => {
    if (e.type == 'mousedown') {
      if (e.target.tagName == 'svg') {
        this.lastMousePos = {x: e.clientX, y: e.clientY, dragging: true};
        e.preventDefault();
      }
    } else if (e.type == 'mousemove') {
      if (this.lastMousePos.dragging) {
        const panX = e.clientX - this.lastMousePos.x;
        const panY = e.clientY - this.lastMousePos.y;
        this.setState(prevState => ({
            offsetX: prevState.offsetX + panX,
            offsetY: prevState.offsetY + panY
        }));
        this.lastMousePos = {x: e.clientX, y: e.clientY, dragging: true};
        e.preventDefault();
      }
    } else if (e.type == 'mouseup') {
      if (this.lastMousePos.dragging) {
        this.lastMousePos = {x: e.clientX, y: e.clientY, dragging: false};
        e.preventDefault();
      }
    }
  }
}

export default DagrePage;
