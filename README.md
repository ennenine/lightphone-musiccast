Assumptions

- Jellyfin is hosted via Docker on the same host as lightphone-musiccast
- Media files are available to both Jellfin and lightphone-musiccast via a common mount point or local folder 
- Playlist names must be alpha-numeric (no special characters)
- Only tested with .m4a files
- Does not test for files in playlist to actually exist before adding to the podcast feed. LightPhone podcast player w
ill crash if it tries to play a file that doesn't exist. 


Setup

1. Customise the ports and volumes in the docker-compose.yml file
2. Customize the configuration variables at the top of the php/index.php file
3. Run docker-compose up -d to start the container
4. Visit the configured web address to see a list of your playlists as podcast feeds to enter into LightPhone dashboar
d 
