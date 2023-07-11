import{_ as R}from"./Authenticated.e87f29e9.js";import{_ as k}from"./Button.55a361b3.js";import D from"./Form.044e48b1.js";import{_ as y,a as Y,b as h}from"./TableData.071ca813.js";import{_ as S,r as Z}from"./SearchInput.610e202d.js";import{_ as N}from"./MultiSelect.20a8799f.js";import{_ as j}from"./TableHeadSort.d087aabb.js";import{h as p,K as $,j as q,f as v,a as o,u as i,w as s,F as K,o as r,Z as z,b as e,c as x,m as u,d,t as c,l as H,O as I}from"./app.09198d16.js";import{r as J}from"./PlusIcon.776cfa19.js";import{r as Q}from"./BackspaceIcon.54694c4a.js";import{r as W}from"./PencilSquareIcon.522896c9.js";import"./open-closed.153f939f.js";import"./use-resolve-button-type.831aaa0f.js";import"./RectangleStackIcon.9284777a.js";import"./DatePicker.a8860f15.js";import"./main.91aa7514.js";import"./Uom.91511293.js";import"./FormInput.55b40d13.js";import"./Modal.f2e318ff.js";import"./ArrowUturnLeftIcon.c908db2b.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.78577212.js";import"./FormTextarea.ada408ae.js";import"./PlusCircleIcon.f3bbcb15.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product (List) ",-1),ee={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},te={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},oe={class:"flex justify-end mb-4"},se=e("span",null," Create ",-1),ae={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},le={key:0},ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ie={class:"flex space-x-1"},ue=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},pe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),ge={class:"font-medium"},he=e("span",null,"to",-1),ve={class:"font-medium"},xe=e("span",null,"of",-1),_e={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},ke={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ce={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Oe={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},$e={class:"bg-white"},Be={class:"flex justify-center"},Le=["src"],Ve={key:0},Se=e("br",null,null,-1),Ne={class:"flex justify-center space-x-1"},je=e("span",null," Edit ",-1),Ke={key:0},Ie=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ue=[Ie],rt={__name:"Index",props:{categories:Object,categoryGroups:Object,operatorOptions:Object,products:Object,uoms:Object},setup(a){const U=a,l=p({code:"",name:"",operator:"",sortKey:"",sortBy:!0,numberPerPage:100}),F=p([]),M=p([]),_=p(!1);$().props.auth.roles;const f=$().props.auth.permissions,b=p([]);$().props.auth.operatorRole;const C=p(),w=p(""),O=p([]);q(()=>{O.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],F.value=[{id:1,value:"Yes"},{id:0,value:"No"}],M.value=[{id:"",value:"All"},{id:"comm",value:"Comm Only"},{id:"sf",value:"SF Only"},{id:"both",value:"Both Comm & SF"}],b.value=[{id:"all",full_name:"All"},...U.operatorOptions.data.map(g=>({id:g.id,full_name:g.full_name}))],l.value.numberPerPage=O.value[0],l.value.operator=b.value[0]});function T(){w.value="create",C.value=null,_.value=!0}function A(g){w.value="update",C.value=g,_.value=!0}function P(){I.get("/products",{...l.value,numberPerPage:l.value.numberPerPage.id,operator_id:l.value.operator.id},{preserveState:!0,replace:!0})}function G(){I.get("/products")}function B(g){l.value.sortKey=g,l.value.sortBy=!l.value.sortBy,P()}function E(){_.value=!1}return(g,n)=>(r(),v(K,null,[o(i(z),{title:"Product"}),o(R,null,{header:s(()=>[X]),default:s(()=>{var L,V;return[e("div",ee,[e("div",te,[e("div",oe,[i(f).includes("create products")?(r(),x(k,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=t=>T())},{default:s(()=>[o(i(J),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})):u("",!0)]),e("div",ae,[o(S,{placeholderStr:"Code",modelValue:l.value.code,"onUpdate:modelValue":n[1]||(n[1]=t=>l.value.code=t)},{default:s(()=>[d(" Code ")]),_:1},8,["modelValue"]),o(S,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":n[2]||(n[2]=t=>l.value.name=t)},{default:s(()=>[d(" Name ")]),_:1},8,["modelValue"]),i(f).includes("admin-access products")?(r(),v("div",le,[ne,o(N,{modelValue:l.value.operator,"onUpdate:modelValue":n[3]||(n[3]=t=>l.value.operator=t),options:b.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):u("",!0)]),e("div",re,[e("div",de,[e("div",ie,[o(k,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[4]||(n[4]=t=>P())},{default:s(()=>[o(i(Z),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),o(k,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[5]||(n[5]=t=>G())},{default:s(()=>[o(i(Q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",pe,[fe,e("span",ge,c((L=a.products.meta.from)!=null?L:0),1),he,e("span",ve,c((V=a.products.meta.to)!=null?V:0),1),xe,e("span",_e,c(a.products.meta.total),1),ye]),o(N,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":n[6]||(n[6]=t=>l.value.numberPerPage=t),options:O.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:P},null,8,["modelValue","options"])])])]),e("div",be,[e("div",ke,[e("div",Ce,[e("table",we,[e("thead",Oe,[e("tr",Pe,[o(y,null,{default:s(()=>[d(" # ")]),_:1}),o(j,{modelName:"code",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:n[7]||(n[7]=t=>B("code"))},{default:s(()=>[d(" Code ")]),_:1},8,["sortKey","sortBy"]),o(j,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:n[8]||(n[8]=t=>B("name"))},{default:s(()=>[d(" Name ")]),_:1},8,["sortKey","sortBy"]),o(y,null,{default:s(()=>[d(" Thumbnail ")]),_:1}),i(f).includes("admin-access products")?(r(),x(y,{key:0},{default:s(()=>[d(" Operator ")]),_:1})):u("",!0),i(f).includes("admin-access products")?(r(),x(y,{key:1},{default:s(()=>[d(" Unit Cost ")]),_:1})):u("",!0),o(y)])]),e("tbody",$e,[(r(!0),v(K,null,H(a.products.data,(t,m)=>(r(),v("tr",{key:t.id,class:"divide-x divide-gray-200"},[o(h,{currentIndex:m,totalLength:a.products.length,inputClass:"text-center"},{default:s(()=>[d(c(a.products.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),o(h,{currentIndex:m,totalLength:a.products.length,inputClass:"text-center"},{default:s(()=>[d(c(t.code),1)]),_:2},1032,["currentIndex","totalLength"]),o(h,{currentIndex:m,totalLength:a.products.length,inputClass:"text-left"},{default:s(()=>[d(c(t.name),1)]),_:2},1032,["currentIndex","totalLength"]),o(h,{currentIndex:m,totalLength:a.products.length,inputClass:"text-center"},{default:s(()=>[e("div",Be,[t.thumbnail?(r(),v("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:t.thumbnail.full_url,alt:""},null,8,Le)):u("",!0)])]),_:2},1032,["currentIndex","totalLength"]),i(f).includes("admin-access products")?(r(),x(h,{key:0,currentIndex:m,totalLength:a.products.length,inputClass:"text-left"},{default:s(()=>[t.operator?(r(),v("span",Ve,[d(c(t.operator.code)+" ",1),Se,d(" "+c(t.operator.name),1)])):u("",!0)]),_:2},1032,["currentIndex","totalLength"])):u("",!0),i(f).includes("admin-access products")?(r(),x(h,{key:1,currentIndex:m,totalLength:a.products.length,inputClass:"text-right"},{default:s(()=>[d(c(t.latestUnitCost?t.latestUnitCost.cost:null),1)]),_:2},1032,["currentIndex","totalLength"])):u("",!0),o(h,{currentIndex:m,totalLength:a.products.length,inputClass:"text-center"},{default:s(()=>[e("div",Ne,[o(k,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:Fe=>A(t)},{default:s(()=>[o(i(W),{class:"w-4 h-4"}),je]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.products.data.length?u("",!0):(r(),v("tr",Ke,Ue))])]),a.products.data.length?(r(),x(Y,{key:0,links:a.products.links,meta:a.products.meta},null,8,["links","meta"])):u("",!0)])])])]),_.value?(r(),x(D,{key:0,product:C.value,categories:a.categories,categoryGroups:a.categoryGroups,uoms:a.uoms,type:w.value,showModal:_.value,operatorOptions:b.value,permissions:i(f),onModalClose:E},null,8,["product","categories","categoryGroups","uoms","type","showModal","operatorOptions","permissions"])):u("",!0)]}),_:1})],64))}};export{rt as default};
