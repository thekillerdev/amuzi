<? header("Content-Type: text/javascript"); ?>

/*global  jPlayerPlaylist: false, jQuery:false */

//(function($, undefined) {
//    'use strict';

<? echo "// it works"; ?>


    var myPlaylist;
    var jplayerCss;
    var jPlaylistTop = null;
    var repeat;
    var current;
    var modalWrapper = "#load-modal-wrapper";

    // Call this from firebug:
    // login('1625795908', 'Diogo Melo', 'melo@vonbraunlabs.com.br', 'kljkjkj');
    function login(facebook_id, name, email, url) {
        $.get('/user/login', {
            facebook_id: facebook_id,
            name: name,
            email: email,
            url: url
        }, function(data) {
            callbackLogin(data);
        });
    }

    function logout() {
        $.get('/user/logout', {
        }, function(data) {
            $('#userId').remove();
            $('.loginRequired').fadeTo('slow', 0.0);
            unloadPlaylist();
            $('.jp-title').css('display', 'none');
        });
    }

    function processLogin() {
        FB.api('/me', function(user) {
            var userDiv = $('#user');
            var loginDiv = $('#login-button');
            console.log(user);
            if(user != null && user.id != null) {
                var image = document.getElementById('image');
                image.src = 'http://graph.facebook.com/' + user.id + '/picture';
                var name = document.getElementById('name');
                name.innerHTML = user.name;
                userDiv.css('visibility', 'visible');
                loginDiv.css('visibility', 'hidden');

                login(user.id, user.first_name + " " + user.last_name, user.email, user.link);
            }
            else {
                userDiv.css('visibility', 'hidden');
                loginDiv.css('visibility', 'visible');
            }
        });
    }


    function callbackLogin(userId) {
        loadPlaylist();
        $('.loginRequired').fadeTo('slow', 1.0);
        $('body').append('<div class="invisible" id="userId">' + userId + '</div>');
    }


    // Soon to be deprecated.
    function savePlaylist() {
        $.post('/playlist/save', {
            playlist: myPlaylist.original,
            name: myPlaylist.name
        }, function(data) {
        });
    }

    function addTrack(trackTitle, trackLink, trackCover) {
        var options;
        var playNow;
        if("undefined" === typeof(trackLink) && "undefined" === typeof(trackCover)) {
            options = {
                playlist: myPlaylist.name,
                id: trackTitle
            };
            playNow = true;
        }
        else {
            options = {
                playlist: myPlaylist.name,
                title: trackTitle,
                url: trackLink,
                cover: trackCover
            };
            playNow = false;
        }

        console.log(options);
        $.post('/playlist/addtrack', options, function(data) {
            $.bootstrapMessageAuto(data[0], data[1]);
            if('error' === data[1])
                loadPlaylist(myPlaylist.name);
            else if('success' === data[1]) {
                var v = data[2];
                myPlaylist.add({title: v.title, url: v.url, free: true, id: v.id}, playNow);
            }
        }, 'json');
    }

    // TODO: take away the playlistName
    function rmTrack(url, playlistName) {
        playlistName = playlistName || 'default';
        $.post('/playlist/rmtrack', {
            playlist: playlistName,
            url: url
        }, function(data) {
            $.bootstrapMessageAuto(data[0], data[1]);
            if('error' === data[1])
                loadPlaylist(playlistName);
        }, 'json');
    }

    /**
     * Load the an specific playlist or the default if an empty string is
     * especified. If a number is given, load the playlist by it's id.
     *
     * @param name Playlist's name, or an empty string to get the default
     * playlist.
     * @return void
     */
    function loadPlaylist(name) {
        name = name || '';
        myPlaylist.removeAll();
        var options;

        if(typeof(name) == 'number' || (typeof(name) == 'string' && parseInt(name) >= 0)) {
            // It's an ID
            if(typeof(name) == 'string')
                name = parseInt(name);
            options = { id: name };
        }
        else {
            // It's a name
            options = { name: name };
        }


        $.post('/playlist/load', options, function(data) {
            if(data != null) {
                $('.jp-title').css('display', 'block');
                $.each(data[0], function(i, v) {
                    myPlaylist.add({title: v.title, mp3: v.url, free: true, id: v.id});
                });
                myPlaylist.name = data[1];
                setRepeatAndCurrent(parseInt(data[2]), parseInt(data[4]));
                setInterfaceShuffle(parseInt(data[3]));
                setTimeout(callbackShuffle, 1500);
            }
        }, 'json').complete(function() {
            if(commands.isRunCommand)
                setTimeout("commands.runProgram()", 1500);
        }).error(function(e) {
            console.log("playlist not available");
        });
    }

    function unloadPlaylist() {
        myPlaylist.name = null;
        myPlaylist.removeAll();
    }

    function rmPlaylist(name) {
        $.post('/playlist/remove', {
            name: name
        }, function(data) {
            $.bootstrapMessageAuto(data[0], data[1]);
        }, 'json');
    }

    function initAmuzi() {
        commands.isRunCommand = true;
        loadPlaylist();
    }

    function setRepeatAndCurrent(repeat, current) {
        myPlaylist.loop = repeat;
        myPlaylist.newCurrent = current;
    }

    function setPlaylistRepeat(name, repeat) {
        name = name || 'default';

    }

    /**
     * When the playlist have more than 8 items it retracts on mouseleave and
     * restore on mouseover.
     */
    function retractablePlaylist() {
        $(jplayerCss).mouseover(function(e) {
            $('.jp-playlist').fadeIn();
        }).mouseleave(function(e) {
            if($('.jp-playlist li').length > 8)
                $('.jp-playlist').fadeOut();
        });
    }

    function callbackPlay(current) {
        $.post('/playlist/setcurrent', {
            name: myPlaylist.name,
            current: current
        }, function(data) {
            if('error' == data[1])
                $.bootstrapMessageAuto(data[0], data[1]);
        }, 'json');
    }

    function callbackShuffle() {
        myPlaylist.setCurrent(myPlaylist.newCurrent);
        setInterfaceRepeat(myPlaylist.loop);
    }

    function applyOverPlaylist() {
        if($('#jp_container_1').length > 0) {
            if(!jPlaylistTop)
                jPlaylistTop = $('.jp-playlist').first().offset().top;
            maxHeight = $(window).height() - jPlaylistTop - 29;
            $('.jp-playlist').css('max-height', maxHeight);
        }
    }

    // Repeat
    function setRepeat(repeat) {
        setInterfaceRepeat(repeat);
        $.post('/playlist/setrepeat', {
            name: myPlaylist.name,
            repeat: repeat
        }, function(data) {
            if('error' == data[1])
                $.bootstrapMessageAuto(data[0], data[1]);
        }, 'json');
    }

    function setInterfaceRepeat(repeat) {
        $('.jp-repeat-off').css('display', repeat ? 'block' : 'none');
        $('.jp-repeat').css('display', repeat ? 'none' : 'block');
    }


    function applyRepeatTriggers() {
        $('.jp-repeat').click(function(e) {
            setRepeat(true);
        });

        $('.jp-repeat-off').click(function(e) {
            setRepeat(false);
        });
    }

    // Shuffle
    function setShuffle(shuffle) {
        $.post('/playlist/setshuffle', {
            name: myPlaylist.name,
            shuffle: shuffle
        }, function(data) {
            if('error' == data[1])
                $.bootstrapMessageAuto(data[0], data[1]);
        }, 'json');
    }

    function setInterfaceShuffle(shuffle) {
        myPlaylist.shuffle(shuffle);
        $('.jp-shuffle-off').css('display', shuffle ? 'block' : 'none');
        $('.jp-shuffle').css('display', shuffle ? 'none' : 'block');
    }

    function applyShuffleTriggers() {
        $('.jp-shuffle').click(function(e) {
            setShuffle(true);
        });

        $('.jp-shuffle-off').click(function(e) {
            setShuffle(false);
        });
    }

    function applyPlaylistSettings() {
        $('.playlistsettings').ajaxForm({
            dataType: 'json',
            success: function (data) {
            },
            beforeSubmit: function() {
            }
        });
    }

    function isLoggedIn() {
        if($('#userId').length)
            return $('#userId').html();
        return false;
    }

    function playlistCallback() {
        $('#playlistsettings').ajaxForm({
            success: function(data) {
                $('#playlistsettings-result tbody').html(data);
            },
            error: function(data) {
                $.bootstrapMessageAuto(data, 'error');
            }
        });
    }

    function newPlaylistCallback() {
        $('form#newPlaylist').ajaxForm({
            dataType: 'json',
            success: function (data) {
                loadPlaylist($('input[name=name]').val());
                $.bootstrapMessageAuto(data[0], data[1]);
                $(modalWrapper).modal('hide');
            },
            error: function(data) {
                $.bootstrapMessageAuto('Error saving. Something went wrong', 'error');
                $('#load-modal-wrapper').modal('hide');
            }
        });
    }



    $(document).ready(function() {
        // topbar menu
        $('.topbar').dropdown();
        // add track into the playlist.
        $('.addplaylist').live('click', function(e) {
            e.preventDefault();
            title = $(this).attr('title');
            mp3 = $(this).attr('href');
            pic = $(this).parent().parent().find('.image img').attr('src');
            addTrack(title, mp3, pic);
        });

        $('.jp-playlist-item-remove').live('click', function(e) {
            url = $(this).parent().parent().find('.jp-playlist-item-free').attr('href');
            rmTrack(url, myPlaylist.name);
        });

        // placeholder on the search input.
        $('#q').placeholder();
        // autocomplete the search input from last.fm.
        $('#q').autocomplete('/api/autocomplete', {
            dateType: 'json',
            parse: function(data) {
                data = $.parseJSON(data);
                return $.map(data, function(row) {
                    return {
                        data: row,
                        value: '<img src="' + row.pic + '"/> <span>' + row.name + '</span>',
                        result: row.name
                    };
                });

            },
            formatItem: function(row, i, n) {
                return '<img src="' + row.pic + '"/> <span>' + row.name + '</span>';
            }
        });

        // submit a query to youtube after change the value of the search input.
        $('#q').change(function() {
            $('#search').submit();
        });

        // start the jplayer.
        jplayerCss = "#jp_container_1";
        myPlaylist = new jPlayerPlaylist({
            jPlayer: "#jquery_jplayer_1",
            cssSelectorAncestor: jplayerCss
        }, [], {supplied: 'mp3', swfPath: "/obj/", free: true, callbackPlay: callbackPlay});

        $(jplayerCss + ' ul:last').sortable({
            update: function() {
                myPlaylist.scan();
                savePlaylist();
            }
        });

        retractablePlaylist();
        applyOverPlaylist();
        applyRepeatTriggers();
        applyShuffleTriggers();
        applyPlaylistSettings();
        $(window).resize(function(e) {
            applyOverPlaylist();
        });

        $('.loadModal').bootstrapLoadModal();

        // For some reason, i can't call loadPlaylist right the way, it must wait for some initialization stuff.
        setTimeout('initAmuzi();', 1500);

        if(isLoggedIn())
            $('.loginRequired').fadeTo('slow', 1.0);

        // playlistsettings -> list
        $('#playlistsettings-result tbody tr.load').live('click', function(e) {
            loadPlaylist($(this).find('.name').html());
            $('#load-modal-wrapper').modal('hide');
        });

        $('#playlistsettings-result tbody tr .public').live('click', function(e) {
            var pub = $(this).val();
            e.stopPropagation();
            var name = $(this).parent().parent().find('.name').html();
            $.post('/playlist/privacy', {
                name: name,
                public: ($(this).attr('checked') === 'checked' ? 'public' : 'private')
            }, function(data) {
                $.bootstrapMessageAuto(data[0], data[1])
                $('#load-modal-wrapper').modal('hide');
            }, 'json');
        });

        // playlistsettings -> remove
        $('#playlistsettings-result tbody tr .remove').live('click', function(e) {
            e.stopPropagation();
            if(confirm('Are you sure?')) {
                var name = $(this).parent().parent().find('.name').html();
                rmPlaylist(name);
                if(name == myPlaylist.name)
                    loadPlaylist('');
                $('#load-modal-wrapper').modal('hide');
            }
        });

        processLogin();
        $('#toc').tableOfContents(null, {startLevel:2});

    });
//})(jQuery);