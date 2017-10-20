/* @flow */
import React, { Component } from 'react';

import * as d3 from 'd3';
import _ from 'lodash';
import { Graph, Rect, Edge, NormalArrow } from './dagre';
import DraggableCore from 'react-draggable';

type Props = {};
type State = {}

class DagrePage extends Component<Props, State> {
  lastMousePos = {x: 0, y: 0, dragging: false}

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
        <DraggableCore>
          <svg width="100%" height="100%">
            <defs>
              <NormalArrow id="markerArrow" />
            </defs>
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
                  Yes
                </text>
              </Edge>
              <Edge markerEnd="url(#markerArrow)" source="foo" target="baz" />
            </Graph>
          </svg>
        </DraggableCore>
      </div>
    )
  }
}

export default DagrePage;
