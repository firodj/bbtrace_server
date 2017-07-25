import React from 'react';
import Button from 'material-ui/Button';
import Grid from 'material-ui/Grid';
import List, { ListItem, ListItemIcon, ListItemText } from 'material-ui/List';

const Root = () => (
  <div>
    <Grid container>
      <Grid item xs={12}>
        <Button raised>
          Default
        </Button>
        <Button raised>
          Default
        </Button>
      </Grid>
    </Grid>
    <Grid container>
      <Grid item xs={3}>
        <List>
          <ListItem button>
            <ListItemText primary="Trash" secondary="First" />
          </ListItem>
          <ListItem button>
            <ListItemText primary="Trash" />
          </ListItem>
          <ListItem button>
            <ListItemText primary="Trash" />
          </ListItem>
        </List>
      </Grid>
    </Grid>
  </div>
);

export default Root;
