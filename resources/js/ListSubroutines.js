import React from 'react';
import List, { ListItem, ListItemText } from 'material-ui/List';
import { purpleAvatar } from './colors';
import Avatar from 'material-ui/Avatar';
import Button from 'material-ui/Button';
import MobileStepper from 'material-ui/MobileStepper';
import KeyboardArrowLeft from 'material-ui-icons/KeyboardArrowLeft';
import KeyboardArrowRight from 'material-ui-icons/KeyboardArrowRight';

const styles = {
  list: {
    overflowY: 'auto',
    height: 'calc(100% - 64px)',
    padding: 0,
  },
  text: {
    width: 224,
    wordWrap: 'break-word'
  }
};

const ListSubroutines = ({ subroutines, activeStep, steps, onClick, onStepperClick }) =>
  <List style={styles.list} dense={true} disablePadding={true}>
  { subroutines.map( (item) =>
    <ListItem button key={item.id} onClick={ () => onClick(item.id) }>
      <Avatar style={ purpleAvatar }>S</Avatar>
      <ListItemText primary={sprintf("%X", item.id)}
        secondary={item.name} style={styles.text} />
    </ListItem>
    ) }
    <MobileStepper
      type="progress"
      steps={steps}
      position="static"
      activeStep={activeStep}
      style={{flexGrow: 1}}
      backButton={
        <Button dense onClick={() => onStepperClick(-1)} disabled={activeStep == 0}>
          <KeyboardArrowLeft />
          Back
        </Button>
      }
      nextButton={
        <Button dense onClick={() => onStepperClick(+1)} disabled={activeStep >= steps-1}>
          <KeyboardArrowRight />
          Next
        </Button>
      }
    />
  </List>
;

export default ListSubroutines;



