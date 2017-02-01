/*
navigator.sayswho= (function(){
    var ua= navigator.userAgent, tem, 
    M= ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
    if(/trident/i.test(M[1])){
        tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
        return 'IE '+(tem[1] || '');
    }
    if(M[1]=== 'Chrome'){
        tem= ua.match(/\b(OPR|Edge)\/(\d+)/);
        if(tem!= null) return tem.slice(1).join(' ').replace('OPR', 'Opera');
    }
    M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
    if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
    return M.join(' ');
})();
*/

navigator.check_browser= (function(){
    var ua= navigator.userAgent, tem, 
    M= ua.match(/(opera|chrome|safari|firefox|edge|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
    if(/trident/i.test(M[1])){
        tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
        return 'IE '+(tem[1] || '');
    }
    if(M[1]=== 'Chrome'){
        tem= ua.match(/\b(OPR|Edge)\/(\d+)/);
        if(tem!= null) return tem.slice(1).join(' ').replace('OPR', 'Opera');
    }
    M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
    if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);

    if ( M[0]=='Firefox' )     { if ( M[1] < 50 ) alert('FireFox 최신버전으로 업데이트 해주세요'); }
    else if ( M[0]=='Chrome' ) { if ( M[1] < 50 ) alert('Chrome 최신버전으로 업데이트 해주세요'); }
    else if ( M[0]=='Safari' ) { if ( M[1] < 10 ) alert('Safari 최신버전으로 업데이트 해주세요'); }
    else if ( M[0]=='Opera' )  { if ( M[1] < 42 ) alert('Opera 최신버전으로 업데이트 해주세요'); }
    else if ( M[0]=='Edge' )   { if ( M[1] < 14 ) alert('Edge 최신버전으로 업데이트 해주세요'); }
    else alert('최신의 Chrome, Firefox, Safari, Opera, Edge 브라우저로 이용해 주세요.');

    return M.join(' ');
})();

