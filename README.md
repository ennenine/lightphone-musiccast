# What is it?

I host all my media (including music) on a local [Jellyfin](https://jellyfin.org) server. I also use a [LightPhone](https://www.thelightphone.com/). It has basic music and podcast fuctionality, and I wanted to be able to listen to my personal media from Jellyfin on it. 

This project publishes all the playlists in Jellyfin as porcast RSS feeds so you can subscribe to them via the podcast functionality on the LightPhone (the music app only supports manually uploading audio files). 



## Features

- All playlists on the Jellyfin server are published as separate RSS feeds
- Each playlist is available as an ordered feed, or randomized feed (to simulate shuffle-play)
- Randomized feeds shuffle the song order each time the podcast feed is refreshed on the LightPhone
- Any changes made to existing playlists are immediately available (after the porcast is refreshed on the LightPhone)
- Validation page for each playlist to inspect what songs are in the playlist by missing from the filesystem (Jellyfin leaves these references in playlists, but hides them in the UI)


## Assumptions

- Jellyfin is hosted via Docker on the same host as lightphone-musiccast
- Media files are available to both Jellfin and lightphone-musiccast via a common mount point or local folder 
- Playlist names must be alpha-numeric (no special characters)
- Only tested with .m4a files
- Does not test for files in playlist to actually exist before adding to the podcast feed. LightPhone podcast player will crash if it tries to play a file that doesn't exist. 



## Screenshots

### Playlist Index

![Playlist Index](images/Jellyfin%20Playlists.png)



### Validation Example

![Playlist Validate](images/Playlist%20Validate.png)



# Setup

1. Customise the ports and volumes in the docker-compose.yml file
2. Copy settings.php.sample to settings.php
3. Customize the configuration variables in the newly created settings.php
4. Run docker-compose up -d to start the container
5. Visit the configured web address to see a list of your playlists as podcast feeds to enter into LightPhone dashboard 


