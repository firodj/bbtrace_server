import React from 'react';
import List, { ListItem, ListItemText } from 'material-ui/List';
import { orangeAvatar } from './colors';
import Avatar from 'material-ui/Avatar';

const ListBlocks = ({ blocks, onClick }) => <div>{ blocks.map( (block) =>
    <ListItem button key={block.id} onClick={() => onClick(block.id)}>
      <Avatar style={ orangeAvatar }>{ block.type[0] }</Avatar>
      <ListItemText primary={sprintf("%X", block.id)}
        secondary={block.type == 'symbol' ? block.symbol_name : (block.function ? block.function.function_name : '-')} />
    </ListItem>
    ) }
  </div>
;

export default ListBlocks;
