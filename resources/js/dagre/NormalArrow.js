/* @flow */

import * as React from 'react';

type Props = {
  id: string
}

class NormalArrow extends React.Component<Props> {
  render() {
    return (
      <marker id={this.props.id}
        viewBox="0 0 10 10"
        refX="9" refY="5"
        markerUnits="strokeWidth"
        markerWidth="8" markerHeight="6"
        orient="auto">
        <path d="M 0 0 L 10 5 L 0 10 z"
          strokeWidth="1"
          strokeDasharray="1, 0"
        />
      </marker>
    );
  }
}

export default NormalArrow;
