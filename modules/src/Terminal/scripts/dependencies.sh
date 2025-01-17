#!/bin/sh

LOGFILE="/pineapple/modules/Terminal/module.log"
TIMESTAMP=`date "+[%Y-%m-%d %H:%M:%S]"`

function add_log {
    echo $1
    echo $TIMESTAMP $1 >> $LOGFILE
}

if [[ "$1" == "" ]]; then
    echo "Argument to script missing! Run with \"dependencies.sh [install|remove]\""
    exit 1
fi

[[ -f /tmp/terminal.progress ]] && {
    exit 0
}

add_log "Starting dependencies script with argument: $1"
touch /tmp/terminal.progress

if [[ "$1" = "install" ]]; then
    opkg update

    check_sd=$(/bin/mount | /bin/grep "on /sd")    
    if [[ -e /sd ]] && [[ -n "$check_sd" ]]; then
        add_log "Installing on sd"

        opkg --dest sd install ttyd >> $LOGFILE
        if [[ $? -ne 0 ]]; then
            add_log "ERROR: opkg --dest sd install ttyd failed"
            rm /tmp/terminal.progress
            exit 1
        fi
    else
        add_log "Installing on disk"

        opkg install ttyd >> $LOGFILE
        if [[ $? -ne 0 ]]; then
            add_log "ERROR: opkg install ttyd failed"
            rm /tmp/terminal.progress
            exit 1
        fi
    fi

    sleep 2
    
    uci set ttyd.@ttyd[0].port=1477
    #uci set ttyd.@ttyd[0].index='/pineapple/modules/Terminal/ttyd/iframe.html'
    uci commit ttyd
    
    /etc/init.d/ttyd disable

    add_log "Installation complete!"
fi

if [[ "$1" = "remove" ]]; then
    add_log "Removing dependencies"

    opkg remove ttyd >> $LOGFILE

    add_log "execute this for manual check 'opkg list-installed | grep ttyd'"
    add_log "Removing complete!"
fi

rm /tmp/terminal.progress