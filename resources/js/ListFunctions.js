import React from 'react';
import List, { ListItem, ListItemText } from 'material-ui/List';
import { purpleAvatar } from './colors';
import Avatar from 'material-ui/Avatar';
import MobileStepper from 'material-ui/MobileStepper';

const listStyle = {
  overflowY: 'auto',
  height: '100vh',
  padding: 0,
};

const ListFunctions = ({ functions, activeStep, steps, onClick, onStepperClick }) =>
  <List style={listStyle} dense={true} disablePadding={true}>
  { functions.map( (item) =>
    <ListItem button key={item.id} onClick={ () => onClick(item.id) }>
      <Avatar style={ purpleAvatar }>{ item.type[0] }</Avatar>
      <ListItemText primary={sprintf("%X", item.id)}
        secondary={item.function_name} />
    </ListItem>
    ) }
    <MobileStepper
      type="progress"
      steps={steps}
      position="static"
      activeStep={activeStep}
      style={{flexGrow: 1}}
      onBack={ () => onStepperClick(-1) }
      onNext={ () => onStepperClick(+1) }
      disableBack={activeStep == 0}
      disableNext={activeStep >= steps-1}
    />
  </List>
;

export default ListFunctions;



