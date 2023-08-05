import{o as c,f as b,b as a,r as ne,a as T,u as A,g as f,U as s,K as re,S as oe,A as K,w as D,F as L,Z as ie,t as y,l as C,d as N,k as R,e as de,c as E,m as Y,v as $,O,n as Q}from"./app.cf23103a.js";import{_ as ue}from"./Authenticated.fc1ada02.js";import{_ as q}from"./Button.a17db711.js";import{Z as ce}from"./main.b48edb6e.js";import{_ as me}from"./Graph.7163450e.js";import{r as fe}from"./ArrowUturnLeftIcon.23a68280.js";import{r as pe}from"./ArrowDownTrayIcon.8b5ef6d7.js";import"./open-closed.e70dff63.js";import"./use-resolve-button-type.451dd918.js";import"./RectangleStackIcon.22e4833a.js";const ge={for:"text",class:"block text-sm font-medium text-gray-700"},he={class:"mt-1"},W={__name:"DatetimePicker",props:{modelValue:[Date,String,Object],minDate:[Date,String,Object],maxDate:[Date,String,Object],enableTimePicker:{type:Boolean,default:!0}},emits:["update:modelValue"],setup(p,{emit:u}){function F(V){u("update:modelValue",V)}return(V,g)=>(c(),b("div",null,[a("label",ge,[ne(V.$slots,"default")]),a("div",he,[T(A(ce),{modelValue:p.modelValue,"onUpdate:modelValue":F,format:"yyyy-MM-dd HH:mm",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:p.minDate,maxDate:p.maxDate,enableTimePicker:p.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"])])]))}},ve={class:"flex flex-col space-y-1"},xe={class:"flex space-x-2 items-center"},_e=a("h2",{class:"font-semibold text-md md:text-xl text-gray-700 leading-tight"}," Vend ID ",-1),be={class:"font-semibold text-xl md:text-2xl text-gray-900 leading-tight"},ye={class:"font-semibold text-md md:text-xl text-gray-700 leading-tight"},ke={class:"p-4 sm:px-6 lg:px-8"},we={class:"flex flex-col items-start pl-1"},De={key:0,class:"font-semibold text-md md:text-lg text-gray-700 leading-tight"},Ce={key:1,class:"font-semibold text-md md:text-lg text-gray-700 leading-tight"},Te={class:"flex space-x-2 font-semibold text-md text-gray-500 leading-tight pl-1"},Se=a("h2",null," to ",-1),Oe={class:"pl-1 py-2 text-left"},Ve=a("label",{for:"text",class:"pt-4 pl-1 block text-sm font-medium text-gray-700"}," Shortcut ",-1),Me={class:"pl-1 py-2 flex space-x-2 overflow-x-scroll"},je={class:"pl-1 py-3 grid grid-cols-1 md:grid-cols-5 gap-2"},Ne={class:"col-span-5 flex space-x-1"},Ee=a("svg",{xmlns:"http://www.w3.org/2000/svg",fill:"none",viewBox:"0 0 24 24","stroke-width":"1.5",stroke:"currentColor",class:"w-4 h-4 pr-1"},[a("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"})],-1),qe=a("span",null," Search ",-1),Ae={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Je=a("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),Be=a("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Ye=[Je,Be],$e=a("span",null," Export Excel ",-1),Fe={class:"px-1 mt-2 flex flex-col"},He={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ue={class:"inline-block min-w-full py-2 align-middle"},Ze={class:"shadow-sm ring-1 ring-black ring-opacity-5"},Pe={class:"p-2 flex space-x-1"},Le={key:0,class:"inline-flex rounded-md shadow-sm"},ze={class:"inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2"},Ge=a("label",{class:"pl-2"},"T1",-1),Ke={key:1,class:"inline-flex rounded-md shadow-sm"},Re={class:"inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2"},Qe=a("label",{class:"pl-2"},"T2",-1),We={key:2,class:"inline-flex rounded-md shadow-sm"},Xe={class:"inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2"},Ie=a("label",{class:"pl-2"},"T3",-1),et={key:3,class:"inline-flex rounded-md shadow-sm"},tt={class:"inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2"},at=a("label",{class:"pl-2"},"T4",-1),ft={__name:"Temp",props:{duration:[Number,String],endDate:String,endDateString:String,request:Object,startDate:String,startDateString:String,type:[String,Object,Array],tempError:[Number,String],fans:[String,Object,Array],vendObj:Object,vendTempsObj:Object,vendFansObj:Object},setup(p){const u=p,F=f([6]),V=f([1,3,7,14]),g=f({datetime_from:u.startDate?u.startDate:s().format("YYYY-MM-DD HH:mm:ss"),datetime_to:u.endDate?u.endDate:s().format("YYYY-MM-DD HH:mm:ss"),duration:u.duration}),X=f([]),M=f([]),I=re().props.auth.permissions,o=f(u.vendObj.data),H=f(),U=f(),m=f([u.type.value]),J=f([u.fans]),z=f(0),B=f(!1),ee=f({scales:{x:{type:"time",time:{displayFormats:{hour:"ha (DD)"},tooltipFormat:"YYMMDD hh:mma"}},y:{type:"linear",display:!0,position:"left",ticks:{callback:function(d,l,n){return d+"\xB0C"}}}},plugins:{title:{display:!0,text:o.value.full_name},tooltip:{callbacks:{label:function(d){var l=d.dataset.label.slice(0,2)||"";return l&&(l+=": "),d.parsed.y!==null&&(l+=d.parsed.y+"\xB0C"),l}}}}}),te=()=>{z.value+=1};oe(()=>{Z()}),K(m,async(d,l)=>{O.visit(route("temp",{id:o.value.id,type:u.type.value,types:d,...g.value}),{only:["vendTempsObj"],preserveState:!0,preserveScroll:!0,replace:!0,onSuccess:n=>{O.reload({only:["vendTempsObj"],preserveState:!0,preserveScroll:!0}),Z()}})}),K(J,async(d,l)=>{O.visit(route("temp",{id:o.value.id,type:u.type.value,types:m.value,fans:d,...g.value}),{only:["vendTempsObj"],preserveState:!0,preserveScroll:!0,replace:!0,onSuccess:n=>{O.reload({only:["vendTempsObj"],preserveState:!0,preserveScroll:!0}),Z()}})});function ae(){O.get("/vends/"+o.value.id+"/temp/"+u.type.value,{...g.value,types:m.value,fans:J.value},{preserveScroll:!0})}function G(d,l){O.get("/vends/"+o.value.id+"/temp/"+u.type.value+"?duration="+d+"&durationType="+l)}function se(){window.history.back()}function Z(){let d=["#E6676B","#36a2eb","#cc65fe","#ffce56"],l=JSON.parse(JSON.stringify(u.vendTempsObj.data)),n=JSON.parse(JSON.stringify(u.vendFansObj.data)),r=[],i=[],P=[];(m.value.length>0||J.value.length>0)&&(m.value.forEach((e,h)=>{let v=l.filter(t=>t.type==e);r[e]=v;let S=[];for(let t=0;t<r[e].length;t++)t>0&&Math.abs(s(r[e][t].created_at).diff(s(r[e][t-1].created_at),"minutes"))>5&&S.push({past:r[e][t-1],current:r[e][t]});if(P[e]=r[e][r[e].length-1].value,S.length&&S.forEach((t,k)=>{let x=s(t.past.created_at).add(5,"minutes");do r[e].push({value:"NaN",created_at:x.format(),type:e}),x=x.add(5,"minutes");while(s(t.current.created_at).diff(x,"minutes")>5)}),r[e][v.length-1]&&s().diff(s(r[e][r[e].length-1].created_at),"minutes")>10){let t={unit:"hours",qty:2},k=s(r[e][0].created_at),_=s(r[e][r[e].length-1].created_at).diff(k,"minutes");_<=60?t={unit:"hours",qty:2}:_>60&&_<=360?t={unit:"hours",qty:3}:_>360&&_<=1440?t={unit:"hours",qty:4}:t={unit:"hours",qty:6};let j=s(r[e][r[e].length-1].created_at).add(t.qty,t.unit);s().diff(j,"minutes")<10&&(t={unit:"hours",qty:2});let w=s(r[e][r[e].length-1].created_at).add(5,"minutes");do r[e].push({value:"NaN",created_at:w.format(),type:e}),w=w.add(5,"minutes");while(j.diff(w,"minutes")>5)}r[e].sort((t,k)=>s(t.created_at).unix()-s(k.created_at).unix())}),H.value=r,J.value.forEach((e,h)=>{let v=n.filter(t=>t.type==e);i[e]=v;let S=[];for(let t=0;t<i[e].length;t++)t>0&&Math.abs(s(i[e][t].created_at).diff(s(i[e][t-1].created_at),"minutes"))>5&&S.push({past:i[e][t-1],current:i[e][t]});if(S.length&&S.forEach((t,k)=>{let x=s(t.past.created_at).add(5,"minutes");do i[e].push({value:"NaN",created_at:x.format(),type:e}),x=x.add(5,"minutes");while(s(t.current.created_at).diff(x,"minutes")>5)}),i[e][v.length-1]&&s().diff(s(i[e][i[e].length-1].created_at),"minutes")>10){let t={unit:"hours",qty:2},k=s(i[e][0].created_at),_=s(i[e][i[e].length-1].created_at).diff(k,"minutes");_<=60?t={unit:"hours",qty:2}:_>60&&_<=360?t={unit:"hours",qty:3}:_>360&&_<=1440?t={unit:"hours",qty:4}:t={unit:"hours",qty:6};let j=s(i[e][i[e].length-1].created_at).add(t.qty,t.unit);s().diff(j,"minutes")<10&&(t={unit:"hours",qty:2});let w=s(i[e][i[e].length-1].created_at).add(5,"minutes");do i[e].push({value:"NaN",created_at:w.format(),type:e}),w=w.add(5,"minutes");while(j.diff(w,"minutes")>5)}i[e].sort((t,k)=>s(t.created_at).unix()-s(k.created_at).unix())}),U.value=i,H.value.length>0&&(M.value=[],H.value.forEach((e,h)=>{M.value.push({label:"T"+h+(P[h]?" ("+P[h]+"\u2103)":""),data:e.map(v=>({x:v.created_at,y:v.value})),borderColor:d[h-1],backgroundColor:d[h-1],tension:.1,spanGaps:!0,yAxisID:"y"})})),U.value.length>0&&(M.value=[],U.value.forEach((e,h)=>{M.value.push({label:"Fan"+h,data:e.map(v=>({x:v.created_at,y:v.value})),borderColor:d[h-1],backgroundColor:d[h-1],tension:.1,spanGaps:!0,yAxisID:"y1"})})),te())}function le(){B.value=!0,axios({method:"get",url:"/vends/"+o.value.id+"/temp/"+u.type.value+"/excel",params:{...g.value,types:m.value},responseType:"blob"}).then(d=>{fileDownload(d.data,"Vending_Temp_"+s().format("YYMMDDhhmmss")+".xlsx"),B.value=!1})}return(d,l)=>(c(),b(L,null,[T(A(ie),{title:"Vending Machine"}),T(ue,null,{header:D(()=>[a("div",ve,[a("div",xe,[_e,a("h2",be,y(o.value.code),1),a("h2",ye,y(p.type.name)+" Temperature ",1)])])]),default:D(()=>[a("div",ke,[a("div",we,[o.value.customer_code?(c(),b("h2",De,y(o.value.customer_code),1)):C("",!0),o.value.customer_name?(c(),b("h2",Ce,y(o.value.customer_name),1)):C("",!0)]),a("div",Te,[a("h2",null,y(p.startDateString),1),Se,a("h2",null,y(p.endDateString),1)]),a("div",Oe,[T(q,{class:"border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 px-7 sm:px-3",onClick:l[0]||(l[0]=n=>se())},{default:D(()=>[T(A(fe),{class:"mr-2 flex-shrink-0 h-4 w-4 text-gray-400 group-hover:text-gray-500","aria-hidden":"true"}),N(" Back ")]),_:1})]),Ve,a("div",Me,[(c(!0),b(L,null,R(F.value,n=>(c(),E(q,{class:Q(["border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3",n==g.value.duration?"outline-none ring-2 ring-indigo-500 ring-offset-2":""]),onClick:r=>G(n,"hour")},{default:D(()=>[N(y(n)+" "+y(n>1?"Hours":"Hour"),1)]),_:2},1032,["class","onClick"]))),256)),(c(!0),b(L,null,R(V.value,n=>(c(),E(q,{class:Q(["border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3",n==g.value.duration?"outline-none ring-2 ring-indigo-500 ring-offset-2":""]),onClick:r=>G(n,"day")},{default:D(()=>[N(y(n)+" "+y(n>1?"Days":"Day"),1)]),_:2},1032,["class","onClick"]))),256))]),a("div",je,[T(W,{modelValue:g.value.datetime_from,"onUpdate:modelValue":l[1]||(l[1]=n=>g.value.datetime_from=n),class:"col-span-5 md:col-span-1"},{default:D(()=>[N(" From ")]),_:1},8,["modelValue"]),T(W,{modelValue:g.value.datetime_to,"onUpdate:modelValue":l[2]||(l[2]=n=>g.value.datetime_to=n),minDate:g.value.datetime_from,class:"col-span-5 md:col-span-1"},{default:D(()=>[N(" To ")]),_:1},8,["modelValue","minDate"]),a("div",Ne,[T(q,{class:"border-transparent bg-green-600 py-3 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-700 px-10 sm:px-3 md:py-2 active:outline-none active:ring-2 active:ring-green-500 active:ring-offset-2",onClick:de(ae,["prevent"])},{default:D(()=>[Ee,qe]),_:1},8,["onClick"]),A(I).includes("export excel")?(c(),E(q,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=n=>le())},{default:D(()=>[B.value?C("",!0):(c(),E(A(pe),{key:0,class:"h-4 w-4","aria-hidden":"true"})),B.value?(c(),b("svg",Ae,Ye)):C("",!0),$e]),_:1})):C("",!0)])]),a("div",Fe,[a("div",He,[a("div",Ue,[a("div",Ze,[a("div",Pe,[o.value.temp&&("t2"in o.value.parameterJson||"t3"in o.value.parameterJson||"t4"in o.value.parameterJson)?(c(),b("span",Le,[a("span",ze,[Y(a("input",{type:"checkbox",value:"1","onUpdate:modelValue":l[4]||(l[4]=n=>m.value=n),class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"},null,512),[[$,m.value]]),Ge])])):C("",!0),"t2"in o.value.parameterJson&&o.value.parameterJson.t2!=p.tempError?(c(),b("span",Ke,[a("span",Re,[Y(a("input",{type:"checkbox",value:"2","onUpdate:modelValue":l[5]||(l[5]=n=>m.value=n),class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"},null,512),[[$,m.value]]),Qe])])):C("",!0),"t3"in o.value.parameterJson&&o.value.parameterJson.t3!=p.tempError?(c(),b("span",We,[a("span",Xe,[Y(a("input",{type:"checkbox",value:"3","onUpdate:modelValue":l[6]||(l[6]=n=>m.value=n),class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"},null,512),[[$,m.value]]),Ie])])):C("",!0),"t4"in o.value.parameterJson&&o.value.parameterJson.t4!=p.tempError?(c(),b("span",et,[a("span",tt,[Y(a("input",{type:"checkbox",value:"4","onUpdate:modelValue":l[7]||(l[7]=n=>m.value=n),class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"},null,512),[[$,m.value]]),at])])):C("",!0)]),(c(),E(me,{key:z.value,type:"line",labels:X.value,datasets:M.value,options:ee.value},null,8,["labels","datasets","options"]))])])])])])]),_:1})],64))}};export{ft as default};
