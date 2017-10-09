import React from 'react';
import List, { ListItem, ListItemText } from 'material-ui/List';
import { orangeAvatar } from './colors';
import Avatar from 'material-ui/Avatar';

const ListBlocks = ({ blocks, onClick }) => <div>{ blocks.map( (block) =>
    <ListItem button key={block.id} onClick={() => onClick(block.id)}>
      <Avatar style={ orangeAvatar }>B</Avatar>
      <ListItemText primary={sprintf("%X", block.id)}
        secondary={block.jump_mnemonic} />
    </ListItem>
    ) }
  </div>
;

export default ListBlocks;
