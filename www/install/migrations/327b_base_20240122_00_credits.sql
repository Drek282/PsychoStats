INSERT INTO `ps_config` (`id`, `conftype`, `section`, `var`, `value`, `label`, `type`, `locked`, `verifycodes`, `options`, `help`) 
    VALUES 
        (2702,'theme','credits','credits','
            <h1>Credits</h1>
            <div>
            <ul>
                <li><strong>Jason Morriss, a.k.a. Stormtrooper</strong>—the original creator of PsychoStats</li>
                <li><strong>Rosenstein</strong>—for contributions to the code, feedback, support, ecouragement and for putting up with a lot of bullshit</li>
                <li><strong>wakachamo, Solomenka and janzagata</strong>—for contributions to the code</li>
                <li><strong>RoboCop from APG</strong>—for feedback, support and encouragement</li>
                <li>PsychoStats makes use of various open source libraries, some precompiled.  Among these libraries are jQuery, the Smarty Template Engine and JpGraph.  Most of the versions used in PsychoStats are obsolete but still functional and secure.  PsychoStats would not function without them and a special debt of gratitude is owed to the creators and maintainers of those libraries.</li>
            </ul>
            </div>',
            'Credits','textarea',0,'','','This is the content of the Credits for PsychoStats.  You can edit this to create your own custom thank you list.  It uses html formatting.'),
        (5017,'theme','credits',NULL,'Credits for PsychoStats.','Credits','none',1,NULL,NULL,NULL);
