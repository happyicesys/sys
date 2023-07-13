import{o as d,f as p,b as e,h as l,K as te,U as me,a as c,u as v,w as f,F as ae,Z as ve,c as b,m as x,P as U,d as D,e as fe,t as g,l as ye,O as z}from"./app.f5e1eb74.js";import{_ as ge}from"./Authenticated.e8065e84.js";import{_ as V}from"./Button.a7270666.js";import{_ as N}from"./Graph.aab40dc3.js";import{_ as P}from"./MultiSelect.f1b48197.js";import{_ as R,r as he}from"./SearchInput.e0c46618.js";import{r as be}from"./BackspaceIcon.734b4752.js";import"./open-closed.6c337fc7.js";import"./use-resolve-button-type.f4e36ec4.js";import"./RectangleStackIcon.f1c317f0.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c8a06cec.js";function _e(w,u){return d(),p("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M14.77 4.21a.75.75 0 01.02 1.06l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 011.08-1.04L10 8.168l3.71-3.938a.75.75 0 011.06-.02zm0 6a.75.75 0 01.02 1.06l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 111.08-1.04L10 14.168l3.71-3.938a.75.75 0 011.06-.02z","clip-rule":"evenodd"})])}function xe(w,u){return d(),p("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M5.23 15.79a.75.75 0 01-.02-1.06l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 11-1.08 1.04L10 11.832 6.29 15.77a.75.75 0 01-1.06.02zm0-6a.75.75 0 01-.02-1.06l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 11-1.08 1.04L10 5.832 6.29 9.77a.75.75 0 01-1.06.02z","clip-rule":"evenodd"})])}const De=e("div",{class:"flex flex-col space-y-1"},[e("div",{class:"flex space-x-2 items-center"}," Dashboard ")],-1),we={class:"p-3"},Ge={class:"max-w-7xl mx-auto sm:px-3 lg:px-2"},Oe={class:"bg-white overflow-hidden shadow-sm sm:rounded-lg"},Se={class:"p-4"},ke=e("span",null," Show Filters ",-1),Ce={key:0,class:"p-4 mx-2"},Ve={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Ne=e("span",{class:"text-[9px]"},' ("," for multiple) ',-1),$e={key:0},je=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Be={key:1},Je=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Ae=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),Te={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Le={class:"mt-3"},Me={class:"flex space-x-1"},Fe=e("span",null," Search ",-1),Ke=e("span",null," Reset ",-1),Ue=e("span",null," Hide Filters ",-1),ze={class:"p-1 bg-white border-b border-gray-200 flex flex-col space-y-6"},Pe={class:"text-center p-2"},Re={class:"flex flex-col md:flex-row pt-5"},Ze={class:"md:basis-1/3 m-1"},Ee={class:"md:basis-2/3 my-1 mx-4 px-4"},He={class:"text-sm flex justify-between"},Ye=e("div",null," Past 7 Days - Top 10 Best Performance ",-1),qe={class:"mt-2 flow-root"},Qe={class:"-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8"},We={class:"inline-block min-w-full py-2 align-middle sm:px-3 lg:px-4"},Xe={class:"overflow-auto shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg"},Ie={class:"min-w-full divide-y divide-gray-300"},et=e("thead",{class:"bg-gray-50"},[e("tr",null,[e("th",{scope:"col",class:"py-2 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6"}," # "),e("th",{scope:"col",class:"px-3 py-2 text-left text-sm font-semibold text-gray-900"}," Vending Machine "),e("th",{scope:"col",class:"px-3 py-2 text-left text-sm font-semibold text-gray-900"}," Amount($) "),e("th",{scope:"col",class:"px-3 py-2 text-left text-sm font-semibold text-gray-900"}," Sales(#) ")])],-1),tt={class:"divide-y divide-gray-200 bg-white"},at={class:"whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6"},st={class:"whitespace-nowrap px-3 py-1 text-sm text-gray-600"},ot={key:0},rt=e("br",null,null,-1),lt={key:1},it={class:"whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3"},nt={class:"whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3"},dt={key:0},ct=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ut=[ct],pt={class:"pt-5"},mt={class:"pt-5"},Ot={__name:"Dashboard",props:{activeMachineGraphData:Object,categories:Object,categoryGroups:Object,dayGraphData:Object,locationTypeOptions:Object,monthGraphData:Object,operatorOptions:Object,productGraphData:Object,performerGraphData:Object,vendCount:Number},setup(w){const u=w,i=l({categories:[],categoryGroups:[],codes:"",customer_code:"",customer_name:"",day_date_from:"",day_date_to:"",locationType:"",operator:""}),Z=l([]),se=l([]),E=l(0),H=l(0),Y=l(0),q=l(0),oe=()=>{E.value+=1},re=()=>{H.value+=1},le=()=>{Y.value+=1},ie=()=>{q.value+=1},$=l([]),Q=te().props.auth.operator,j=l([]),B=te().props.auth.permissions,G=l(!1),W=l([]),O=l([]),J=l([]),ne=l({scales:{x:{ticks:{min:1,max:31,stepSize:1}},y:{position:"left",title:{display:!0,text:"Sales($)"},beginAtZero:!0},y1:{position:"right",title:{display:!0,text:"Sales(#)"},beginAtZero:!0}},plugins:{title:{display:!0,text:"Sales by Days"},legend:{reverse:!0}}}),X=l([]),S=l([]),A=l([]),de=l({scales:{x:{ticks:{min:1,max:12,stepSize:1}},y:{position:"left",title:{display:!0,text:"Sales($)"},beginAtZero:!0},y1:{position:"right",title:{display:!0,text:"Sales(#)"},beginAtZero:!0}},plugins:{title:{display:!0,text:"Sales by Months"},legend:{reverse:!0}}}),k=l([]),T=l([]),L=l([]),ce=l({plugins:{legend:{display:!1},title:{display:!0,text:"Past 7 Days - 10 Best Sellers"}}}),M=l([]),I=l([]),F=l([]),K=l([]),ue=l({scales:{x:{ticks:{min:1,max:12,stepSize:1}},y:{position:"left",title:{display:!0,text:"Count(#)"},beginAtZero:!0}},plugins:{title:{display:!0,text:"VM Deployment Count By Month"}}});me(()=>{Z.value=u.categories.data.map(t=>({id:t.id,name:t.name})),se.value=u.categoryGroups.data.map(t=>({id:t.id,name:t.name})),$.value=[{id:"all",name:"All"},...u.locationTypeOptions.data.map(t=>({id:t.id,name:t.name}))],j.value=[{id:"all",name:"All"},...u.operatorOptions.data.map(t=>({id:t.id,name:t.name}))],i.value.locationType=$.value[0],i.value.operator=j.value.find(t=>t.id==Q.id),ee()});function n(t,o){var a=parseInt(t.slice(1,3),16),y=parseInt(t.slice(3,5),16),h=parseInt(t.slice(5,7),16);return"rgba("+a+", "+y+", "+h+", "+o+")"}function C(){z.visit(route("dashboard",{...i.value,categories:i.value.categories.map(t=>t.id),categoryGroups:i.value.categoryGroups.map(t=>t.id),location_type_id:i.value.locationType.id,operator_id:i.value.operator.id}),{only:["activeMachineGraphData","dayGraphData","monthGraphData","productGraphData","performerGraphData","vendCount"],preserveState:!0,preserveScroll:!0,replace:!0,onSuccess:t=>{z.reload({only:["activeMachineGraphData","dayGraphData","monthGraphData","productGraphData","performerGraphData","vendCount"],preserveState:!0,preserveScroll:!0}),ee()}})}function pe(){z.get("/dashboard",{},{preserveState:!1,preserveScroll:!0})}function ee(){I.value=[],F.value=[],K.value=[],W.value=[],O.value=[],J.value=[],X.value=[],S.value=[],A.value=[],k.value=[],T.value=[],L.value=[];let t=["#3e95cd","#ff7f7f","#007500","#808080","#c45850"],o=["#37a2eb","#ff6384","#4cc1c0","#ff9f40","#9a66ff","#ffcd56","#c9cbcf"];W.value=JSON.parse(JSON.stringify(u.dayGraphData));let a=[];a=_.groupBy(JSON.parse(JSON.stringify(u.dayGraphData)).data,"month_name"),Object.keys(a).forEach((r,s)=>{O.value.push({label:r+" (#)",data:a[r].map(m=>m.count),backgroundColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),borderColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),yAxisID:"y1",type:"line",order:1}),O.value.push({label:r+" ($)",data:a[r].map(m=>m.amount),backgroundColor:s%2==0?n(t[s],.2):n(t[s],1),borderColor:s%2==0?n(t[s],.2):n(t[s],1),fill:!1,yAxisID:"y",type:"bar",order:2})});for(let r=1;r<=31;r++)J.value.push(r);X.value=JSON.parse(JSON.stringify(u.monthGraphData));let y=[];y=JSON.parse(JSON.stringify(u.monthGraphData)),Object.keys(y).forEach((r,s)=>{S.value.push({label:r+" (#)",data:Object.values(y[r]).map(m=>m.count),backgroundColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),borderColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),yAxisID:"y1",type:"line",order:1}),S.value.push({label:r+" ($)",data:Object.values(y[r]).map(m=>m.amount),backgroundColor:s%2==0?n(t[s],.2):n(t[s],1),borderColor:s%2==0?n(t[s],.2):n(t[s],1),fill:!1,yAxisID:"y",type:"bar",order:2})});for(let r=1;r<=12;r++)A.value.push(r);k.value=JSON.parse(JSON.stringify(u.productGraphData)),T.value.push({label:"Sales",data:k.value.data.map(r=>r.count),backgroundColor:o}),L.value=k.value.data.map(r=>r.product?r.product.code+" - "+r.product.name:null),M.value=JSON.parse(JSON.stringify(u.performerGraphData)),I.value=JSON.parse(JSON.stringify(u.activeMachineGraphData));let h=[];h=JSON.parse(JSON.stringify(u.activeMachineGraphData)),Object.keys(h).forEach((r,s)=>{F.value.push({label:r+" (#)",data:Object.values(h[r]).map(m=>m.count),backgroundColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),borderColor:s%2==0?n(t[s+2],.2):n(t[s+2],.9),type:"line"})});for(let r=1;r<=12;r++)K.value.push(r);oe(),re(),le(),ie()}return(t,o)=>(d(),p(ae,null,[c(v(ve),{title:"Dashboard"}),c(ge,null,{header:f(()=>[De]),default:f(()=>[e("div",we,[e("div",Ge,[e("div",Oe,[e("div",Se,[!G.value&&v(B).includes("admin-access vends")?(d(),b(V,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-3 md:px-4 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=a=>G.value=!0)},{default:f(()=>[c(v(_e),{class:"h-4 w-4","aria-hidden":"true"}),ke]),_:1})):x("",!0)]),G.value?(d(),p("div",Ce,[e("div",Ve,[c(R,{placeholderStr:"Vend ID",modelValue:i.value.codes,"onUpdate:modelValue":o[1]||(o[1]=a=>i.value.codes=a),onKeyup:o[2]||(o[2]=U(a=>C(),["enter"]))},{default:f(()=>[D(" Vend ID "),Ne]),_:1},8,["modelValue"]),c(R,{placeholderStr:"Cust ID",modelValue:i.value.customer_code,"onUpdate:modelValue":o[3]||(o[3]=a=>i.value.customer_code=a),onKeyup:o[4]||(o[4]=U(a=>C(),["enter"]))},{default:f(()=>[D(" Cust ID ")]),_:1},8,["modelValue"]),c(R,{placeholderStr:"Cust Name",modelValue:i.value.customer_name,"onUpdate:modelValue":o[5]||(o[5]=a=>i.value.customer_name=a),onKeyup:o[6]||(o[6]=U(a=>C(),["enter"]))},{default:f(()=>[D(" Cust Name ")]),_:1},8,["modelValue"]),v(B).includes("admin-access vends")?(d(),p("div",$e,[je,c(P,{modelValue:i.value.categories,"onUpdate:modelValue":o[7]||(o[7]=a=>i.value.categories=a),options:Z.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):x("",!0),v(B).includes("admin-access vends")?(d(),p("div",Be,[Je,c(P,{modelValue:i.value.operator,"onUpdate:modelValue":o[8]||(o[8]=a=>i.value.operator=a),options:j.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):x("",!0),e("div",null,[Ae,c(P,{modelValue:i.value.locationType,"onUpdate:modelValue":o[9]||(o[9]=a=>i.value.locationType=a),options:$.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",Te,[e("div",Le,[e("div",Me,[c(V,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[10]||(o[10]=fe(a=>C(),["prevent"]))},{default:f(()=>[c(v(he),{class:"h-4 w-4","aria-hidden":"true"}),Fe]),_:1}),c(V,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[11]||(o[11]=a=>pe())},{default:f(()=>[c(v(be),{class:"h-4 w-4","aria-hidden":"true"}),Ke]),_:1}),c(V,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[12]||(o[12]=a=>G.value=!1)},{default:f(()=>[c(v(xe),{class:"h-4 w-4","aria-hidden":"true"}),Ue]),_:1})])])])])):x("",!0),e("div",ze,[e("p",Pe,g(i.value&&i.value.operator?i.value.operator.name:v(Q).name),1),(d(),b(N,{key:E.value,type:"scatter",labels:J.value,datasets:O.value,options:ne.value},null,8,["labels","datasets","options"])),e("div",Re,[e("div",Ze,[(d(),b(N,{key:H.value,type:"pie",labels:L.value,datasets:T.value,options:ce.value},null,8,["labels","datasets","options"]))]),e("div",Ee,[e("p",He,[Ye,e("div",null," Based on "+g(w.vendCount)+" active machine(s) ",1)]),e("div",qe,[e("div",Qe,[e("div",We,[e("div",Xe,[e("table",Ie,[et,e("tbody",tt,[(d(!0),p(ae,null,ye(M.value.data,(a,y)=>(d(),p("tr",{key:a.id},[e("td",at,g(y+1),1),e("td",st,[a.customer?(d(),p("span",ot,[D(g(a.customer.code)+" ",1),rt,D(" "+g(a.customer.name),1)])):(d(),p("span",lt,g(a.name),1))]),e("td",it,g(a.amount.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1),e("td",nt,g(a.count),1)]))),128)),M.value.data.length?x("",!0):(d(),p("tr",dt,ut))])])])])])])])]),e("div",pt,[(d(),b(N,{key:Y.value,type:"scatter",labels:A.value,datasets:S.value,options:de.value},null,8,["labels","datasets","options"]))]),e("div",mt,[(d(),b(N,{key:q.value,type:"scatter",labels:K.value,datasets:F.value,options:ue.value},null,8,["labels","datasets","options"]))])])])])])]),_:1})],64))}};export{Ot as default};
